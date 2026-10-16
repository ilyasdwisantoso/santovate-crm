<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
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
                    ->select(array_merge(Prospect::identityColumns(),['organization_id'])),
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
            ->get(array_merge(Prospect::identityColumns(),['phone_normalized']))->groupBy('phone_normalized');
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
                'prospect'=>$contact->prospect ? ['id'=>$contact->prospect->id,'name'=>$contact->prospect->display_name,'entity_type'=>$contact->prospect->entity_type] : null,
                'matching_prospects'=>($matches->get($contact->phone_normalized) ?? collect())
                    ->map(fn ($p) => ['id'=>$p->id,'name'=>$p->display_name,'entity_type'=>$p->entity_type])->values()->all(),
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
                    'lead_kind'=>$locked->prospect_id ? Prospect::where('organization_id',$locked->organization_id)->findOrFail($locked->prospect_id)->entity_type : $data['lead_kind'],'assigned_to'=>$assignee?->id,
                    'notes'=>$data['notes'] ?? null,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]
            );
        });
        return back()->with('success', 'Review lead WhatsApp berhasil disimpan.');
    }

    public function link(Request $request, WhatsAppInboundContact $contact): RedirectResponse
    {
        $this->assertTenant($request,$contact);
        $data=$request->validate(['prospect_id'=>['required','integer']]);
        app(\App\Services\WhatsAppLeadConversionService::class)->link($request->user(),$contact,(int)$data['prospect_id']);
        return back()->with('success','Kontak terhubung ke prospek.');
    }

    public function convert(Request $request, WhatsAppInboundContact $contact, EntitlementService $entitlements): RedirectResponse
    {
        $p=app(\App\Services\WhatsAppLeadConversionService::class)->convert($request->user(),$contact,$request->all(),$entitlements);
        return redirect()->route('prospects.show',$p)->with('success','Prospek berhasil dibuat dari kontak WhatsApp.');
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
}
