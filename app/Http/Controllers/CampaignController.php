<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppCampaignRecipient;
use App\Models\FollowUpTemplate;
use App\Models\Prospect;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $missing = $this->missingSchema();
        if ($missing) {
            return Inertia::render('Campaigns/Index', [
                'campaigns'=>[],
                'templates'=>[],
                'eligibleCount'=>0,
                'setupReady'=>false,
                'missingSchema'=>$missing,
                'channelReady'=>false,
                'channel'=>null,
            ]);
        }

        $organizationId = $request->user()->organization_id;

        $campaigns = WhatsAppCampaign::query()
            ->where('organization_id', $organizationId)
            ->with(['template:id,name,meta_template_name,meta_status'])
            ->withCount('recipients')
            ->latest('id')
            ->get();

        // Only approved Meta templates are selectable. Draft templates previously
        // appeared here and then failed on submit, which looked like a broken page.
        $templates = FollowUpTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('meta_status', 'approved')
            ->whereNotNull('meta_template_name')
            ->orderBy('name')
            ->get(['id','name','meta_template_name','meta_language','meta_status']);

        $eligibleCount = Prospect::query()
            ->visibleTo($request->user())
            ->whereNotNull('phone_normalized')
            ->whereNotNull('whatsapp_opt_in_at')
            ->whereNull('whatsapp_opt_out_at')
            ->count();

        $channel = null;
        $channelReady = false;
        try {
            $channelModel = WhatsAppChannel::query()->where('organization_id', $organizationId)->first();
            if ($channelModel) {
                $channelReady = $channelModel->ready();
                $channel = [
                    'provider'=>$channelModel->provider,
                    'phone_number_id'=>$channelModel->phone_number_id,
                    'waba_id'=>$channelModel->waba_id,
                    'is_active'=>(bool)$channelModel->is_active,
                    'verified_at'=>$channelModel->verified_at?->toIso8601String(),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
            // Keep the campaign screen usable even if an old encrypted token is invalid.
            $channelReady = false;
        }

        return Inertia::render('Campaigns/Index', [
            'campaigns'=>$campaigns,
            'templates'=>$templates,
            'eligibleCount'=>$eligibleCount,
            'setupReady'=>true,
            'missingSchema'=>[],
            'channelReady'=>$channelReady,
            'channel'=>$channel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($missing = $this->missingSchema()) {
            return back()->with('error', 'Schema WA Campaign belum siap: '.implode(', ', $missing).'. Jalankan php artisan migrate.');
        }

        $data = $request->validate([
            'name'=>['required','string','max:180'],
            'template_id'=>['required','integer'],
        ]);

        $template = FollowUpTemplate::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->where('meta_status', 'approved')
            ->whereNotNull('meta_template_name')
            ->findOrFail($data['template_id']);

        $campaign = WhatsAppCampaign::create([
            'organization_id'=>$request->user()->organization_id,
            'follow_up_template_id'=>$template->id,
            'created_by'=>$request->user()->id,
            'name'=>$data['name'],
            'status'=>'draft',
        ]);

        $prospects = Prospect::query()
            ->visibleTo($request->user())
            ->whereNotNull('phone_normalized')
            ->whereNotNull('whatsapp_opt_in_at')
            ->whereNull('whatsapp_opt_out_at')
            ->pluck('id');

        foreach ($prospects as $prospectId) {
            $campaign->recipients()->firstOrCreate(
                ['prospect_id'=>$prospectId],
                ['status'=>'queued']
            );
        }

        $campaign->update(['total_recipients'=>$prospects->count()]);

        return back()->with('success', 'Campaign dibuat dengan '.$prospects->count().' penerima opt-in.');
    }

    public function send(Request $request, WhatsAppCampaign $campaign): RedirectResponse
    {
        abort_unless((int)$campaign->organization_id === (int)$request->user()->organization_id, 403);

        if ($missing = $this->missingSchema()) {
            return back()->with('error', 'Schema WA Campaign belum siap: '.implode(', ', $missing).'.');
        }

        if ($campaign->status === 'running') {
            return back()->with('error', 'Campaign sudah berjalan.');
        }

        $channel = WhatsAppChannel::query()->where('organization_id', $request->user()->organization_id)->first();
        try {
            if (!$channel || !$channel->ready()) {
                return back()->with('error', 'WhatsApp Cloud API belum aktif. Lengkapi Phone Number ID dan Access Token di menu WhatsApp API.');
            }
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Konfigurasi WhatsApp API tidak dapat dibaca. Simpan ulang Access Token di menu WhatsApp API.');
        }

        $campaign->load('template');
        if (!$campaign->template || $campaign->template->meta_status !== 'approved' || blank($campaign->template->meta_template_name)) {
            return back()->with('error', 'Campaign membutuhkan Meta template berstatus approved.');
        }

        $queuedIds = $campaign->recipients()->where('status', 'queued')->pluck('id');
        if ($queuedIds->isEmpty()) {
            $campaign->update(['status'=>'finished','finished_at'=>now()]);
            return back()->with('error', 'Tidak ada recipient queued yang bisa dikirim.');
        }

        $campaign->update(['status'=>'running','started_at'=>now(),'finished_at'=>null]);
        foreach ($queuedIds as $recipientId) {
            SendWhatsAppCampaignRecipient::dispatch($recipientId);
        }

        return back()->with('success', 'Campaign masuk queue. Pastikan queue worker aktif untuk memproses pengiriman.');
    }

    private function missingSchema(): array
    {
        $requirements = [
            'whatsapp_campaigns'=>['id','organization_id','follow_up_template_id','created_by','name','status','total_recipients'],
            'whatsapp_campaign_recipients'=>['id','whatsapp_campaign_id','prospect_id','status'],
            'follow_up_templates'=>['id','organization_id','meta_template_name','meta_status','is_active'],
            'prospects'=>['id','organization_id','phone_normalized','whatsapp_opt_in_at','whatsapp_opt_out_at'],
            'whatsapp_channels'=>['id','organization_id','phone_number_id','access_token','is_active'],
            'whatsapp_messages'=>['id','organization_id','prospect_id','status','provider_message_id'],
        ];

        $missing = [];
        foreach ($requirements as $table=>$columns) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
                continue;
            }
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) $missing[] = $table.'.'.$column;
            }
        }

        return array_values(array_unique($missing));
    }
}
