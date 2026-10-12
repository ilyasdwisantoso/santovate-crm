<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Models\WhatsAppInboundContact;
use App\Models\WhatsAppLeadReview;
use App\Models\WhatsAppMessage;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only, tenant-scoped lead review. No Meta API calls are made here. */
class WhatsAppLeadReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = (int) $user->organization_id;
        $search = $request->query('q');
        $search = is_string($search) ? mb_substr(trim($search), 0, 80) : '';
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, ['pending','qualified','ignored','linked'], true) ? $status : 'pending';

        $query = WhatsAppInboundContact::query()->where('organization_id', $orgId)
            ->with([
                'review' => fn ($q) => $q->where('organization_id', $orgId)
                    ->with(['assignee' => fn ($u) => $u->where('organization_id', $orgId)]),
                'prospect' => fn ($q) => $q->where('organization_id', $orgId)
                    ->select(['id','company_name','organization_id']),
            ]);
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(fn ($q) => $q->where('phone_normalized','like','%'.$escaped.'%')
                ->orWhere('profile_name','like','%'.$escaped.'%'));
        }
        if ($status === 'linked') $query->whereNotNull('prospect_id');
        elseif ($status === 'pending') $query->where(fn ($q) => $q->whereDoesntHave('review', fn ($r) => $r->where('organization_id',$orgId))
            ->orWhereHas('review', fn ($r) => $r->where('organization_id',$orgId)->where('status','pending')));
        else $query->whereHas('review', fn ($q) => $q->where('organization_id',$orgId)->where('status', $status));

        $contacts = $query->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(15)->appends(['q'=>$search,'status'=>$status]);
        $rows = $contacts->getCollection();
        $phones = $rows->pluck('phone_normalized')->filter()->unique()->values()->all();
        $lastIds = $rows->pluck('last_message_id')->filter()->unique()->values()->all();
        $lastMessages = WhatsAppMessage::query()->where('organization_id',$orgId)
            ->whereIn('provider_message_id',$lastIds)->get(['provider_message_id','body'])
            ->keyBy('provider_message_id');
        $matches = Prospect::query()->where('organization_id',$orgId)
            ->whereIn('phone_normalized',$phones)->orderBy('id')->limit(250)
            ->get(['id','company_name','phone_normalized'])->groupBy('phone_normalized');
        $contacts->setCollection($rows->map(function ($contact) use ($lastMessages,$matches) {
            $review = $contact->review;
            return [
                'id'=>$contact->id, 'phone'=>$contact->phone_normalized,
                'profile_name'=>$contact->profile_name,
                'source_name'=>$contact->source_name ?: 'WhatsApp (asal belum pasti)',
                'source_id'=>$contact->source_id,
                'last_message_at'=>$contact->last_message_at?->toIso8601String(),
                'last_message'=>mb_substr((string)($lastMessages->get($contact->last_message_id)?->body ?? ''), 0, 1000),
                'status'=>$review?->status ?: 'pending',
                'lead_kind'=>$review?->lead_kind ?: 'unknown',
                'notes'=>$review?->notes ?: '',
                'assigned_to'=>$review?->assigned_to,
                'assignee_name'=>$review?->assignee?->name,
                'prospect'=>$contact->prospect ? ['id'=>$contact->prospect->id,'name'=>$contact->prospect->company_name] : null,
                'matching_prospects'=>($matches->get($contact->phone_normalized) ?? collect())
                    ->map(fn ($p) => ['id'=>$p->id,'name'=>$p->company_name])->values()->all(),
            ];
        }));

        return Inertia::render('WhatsApp/Leads', [
            'contacts'=>$contacts,
            'filters'=>['q'=>$search,'status'=>$status],
            'salesUsers'=>User::query()->where('organization_id',$orgId)
                ->where('role','sales')->where('is_active',true)
                ->orderBy('name')->get(['id','name']),
        ]);
    }

    public function review(Request $request, WhatsAppInboundContact $contact): RedirectResponse
    {
        $this->assertTenant($request, $contact);
        $data = $request->validate([
            'status'=>['required',Rule::in(['pending','qualified','ignored'])],
            'lead_kind'=>['required',Rule::in(['unknown','individual','business'])],
            'assigned_to'=>['nullable','integer'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $assignee = $this->assignee($request, $data['assigned_to'] ?? null);
        DB::transaction(function () use ($request,$contact,$data,$assignee) {
            $locked = WhatsAppInboundContact::query()->where('organization_id',$request->user()->organization_id)
                ->lockForUpdate()->findOrFail($contact->id);
            WhatsAppLeadReview::updateOrCreate(
                ['whatsapp_inbound_contact_id'=>$locked->id],
                ['organization_id'=>$locked->organization_id,'status'=>$data['status'],
                    'lead_kind'=>$data['lead_kind'],'assigned_to'=>$assignee?->id,
                    'notes'=>$data['notes'] ?? null,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]
            );
        });
        return back()->with('success', 'Review lead WhatsApp berhasil disimpan.');
    }

    public function link(Request $request, WhatsAppInboundContact $contact): RedirectResponse
    {
        $this->assertTenant($request, $contact);
        $data = $request->validate(['prospect_id'=>['required','integer']]);
        DB::transaction(function () use ($request, $contact, $data) {
            $locked = WhatsAppInboundContact::query()->where('organization_id',$request->user()->organization_id)
                ->lockForUpdate()->findOrFail($contact->id);
            if ($locked->prospect_id) throw ValidationException::withMessages(['prospect_id'=>'Kontak sudah terhubung dengan prospek.']);
            $p = Prospect::query()->where('organization_id',$locked->organization_id)->findOrFail($data['prospect_id']);
            if ($p->phone_normalized !== $locked->phone_normalized)
                throw ValidationException::withMessages(['prospect_id'=>'Nomor prospek tidak cocok dengan kontak WhatsApp.']);
            $locked->update(['prospect_id'=>$p->id]);
            $this->linkMessages($locked, $p);
            $this->markQualified($request, $locked, 'business');
        });
        return back()->with('success','Kontak terhubung ke prospek lama.');
    }

    public function convert(Request $request, WhatsAppInboundContact $contact, EntitlementService $entitlements): RedirectResponse
    {
        $this->assertTenant($request, $contact);
        $data = $request->validate([
            'company_name'=>['required','string','min:2','max:255'],
            'city'=>['nullable','string','max:255'],
        ]);
        $company = trim($data['company_name']);
        $city = trim($data['city'] ?? '');
        if ($company === '') throw ValidationException::withMessages(['company_name'=>'Nama perusahaan wajib diisi.']);

        $createdId = DB::transaction(function () use ($request,$contact,$company,$city,$entitlements) {
            $orgId = (int)$request->user()->organization_id;
            $org = Organization::query()->whereKey($orgId)->lockForUpdate()->firstOrFail();
            $locked = WhatsAppInboundContact::query()->where('organization_id',$orgId)
                ->lockForUpdate()->findOrFail($contact->id);
            if ($locked->prospect_id) throw ValidationException::withMessages(['company_name'=>'Lead sudah memiliki prospek.']);
            $key = Prospect::makeCompanyKey($company, $city, $orgId);
            if (Prospect::query()->where('organization_id',$orgId)->where('company_key',$key)->exists())
                throw ValidationException::withMessages(['company_name'=>'Perusahaan sudah tercatat; gunakan tombol Hubungkan Prospek.']);
            if (Prospect::query()->where('organization_id',$orgId)->where('phone_normalized',$locked->phone_normalized)->exists())
                throw ValidationException::withMessages(['company_name'=>'Nomor WhatsApp sudah terkait prospek; hubungkan prospek yang ada agar tidak duplikat.']);
            // Quota is enforced at conversion, NEVER while preserving inbound messages.
            $entitlements->assertCanConsume($org,'prospects',1);
            $review = WhatsAppLeadReview::where('organization_id',$orgId)
                ->where('whatsapp_inbound_contact_id',$locked->id)->first();
            $assigned = $review?->assigned_to;
            if ($assigned && !User::query()->where('organization_id',$orgId)->where('role','sales')->where('is_active',true)->whereKey($assigned)->exists()) $assigned = null;
            $p = Prospect::create([
                'organization_id'=>$orgId, 'company_name'=>$company, 'city'=>$city ?: null,
                'contact_name'=>$locked->profile_name,
                'phone'=>$locked->phone_normalized, 'assigned_to'=>$assigned,
                'created_by'=>$request->user()->id, 'status'=>'baru',
                'source_name'=>$locked->source_name ?: 'WhatsApp', 'source_url'=>$locked->source_url,
                'whatsapp_status'=>'valid', 'whatsapp_id'=>$locked->wa_id,
                'whatsapp_verified_at'=>now(), 'last_customer_reply_at'=>$locked->last_message_at,
                'last_contact_at'=>$locked->last_message_at,
            ]);
            $locked->update(['prospect_id'=>$p->id]);
            $this->linkMessages($locked,$p);
            $this->markQualified($request,$locked,'business');
            ProspectActivity::create([
                'prospect_id'=>$p->id,'user_id'=>$request->user()->id,'type'=>'note',
                'title'=>'Dikonversi dari lead WhatsApp',
                'description'=>'Lead WhatsApp ditinjau dan dikonversi menjadi prospek bisnis.',
                'occurred_at'=>now(),
            ]);
            return $p->id;
        });
        return redirect()->route('prospects.show',$createdId)->with('success','Prospek bisnis berhasil dibuat dari kontak WhatsApp.');
    }

    private function assertTenant(Request $request, WhatsAppInboundContact $contact): void
    {
        abort_unless((int)$contact->organization_id === (int)$request->user()->organization_id,404);
    }
    private function assignee(Request $request, mixed $id): ?User
    {
        if ($id === null || $id === '') return null;
        $user = User::query()->where('organization_id',$request->user()->organization_id)
            ->where('role','sales')->where('is_active',true)->find($id);
        if (!$user) throw ValidationException::withMessages(['assigned_to'=>'Account Executive tidak valid untuk workspace ini.']);
        return $user;
    }
    private function linkMessages(WhatsAppInboundContact $contact, Prospect $prospect): void
    {
        WhatsAppMessage::query()->where('organization_id',$contact->organization_id)
            ->where('direction','inbound')->where('from_phone',$contact->phone_normalized)
            ->whereNull('prospect_id')->update(['prospect_id'=>$prospect->id]);
    }
    private function markQualified(Request $request, WhatsAppInboundContact $contact, string $kind): void
    {
        WhatsAppLeadReview::updateOrCreate(
            ['whatsapp_inbound_contact_id'=>$contact->id],
            ['organization_id'=>$contact->organization_id,'status'=>'qualified','lead_kind'=>$kind,
                'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]
        );
    }
}
