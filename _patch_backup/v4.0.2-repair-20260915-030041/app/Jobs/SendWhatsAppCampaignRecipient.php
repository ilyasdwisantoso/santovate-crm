<?php
namespace App\Jobs;
use App\Models\WhatsAppCampaignRecipient;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SendWhatsAppCampaignRecipient implements ShouldQueue {
    use Queueable;
    public int $tries=2;
    public function __construct(public int $recipientId) {}
    public function handle(WhatsAppService $whatsapp): void {
        $recipient=WhatsAppCampaignRecipient::find($this->recipientId);
        if (!$recipient || $recipient->status !== 'queued') return;
        $campaign=$recipient->campaign()->with('template')->first();
        $prospect=$recipient->prospect()->with(['assignedUser','creator','organization','products'])->first();
        if (!$campaign || !$campaign->template || !$prospect) return;
        $actor=$prospect->assignedUser ?: $prospect->creator;
        if (!$actor) {
            $actor=$prospect->organization?->users()->where('role','admin')->first();
        }
        if (!$actor) { $recipient->update(['status'=>'skipped','error'=>'Tidak ada user pengirim.']); $this->finishIfDone($campaign); return; }
        if (!$prospect->whatsapp_opt_in_at || $prospect->whatsapp_opt_out_at) {
            $recipient->update(['status'=>'skipped','error'=>'Kontak tidak memiliki opt-in aktif.']); $this->finishIfDone($campaign); return;
        }
        try {
            $message=$whatsapp->send($prospect,$actor,$campaign->template,null,$recipient->id);
            $recipient->update(['status'=>'sent','provider_message_id'=>$message->provider_message_id,'sent_at'=>now()]);
            $campaign->increment('sent_count');
        } catch (\Throwable $e) {
            report($e);
            $recipient->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,2000)]);
            $campaign->increment('failed_count');
        }
        $this->finishIfDone($campaign);
    }
    private function finishIfDone($campaign): void {
        if (!$campaign->recipients()->whereIn('status',['queued','processing'])->exists()) {
            $campaign->update(['status'=>'finished','finished_at'=>now()]);
        }
    }
}
