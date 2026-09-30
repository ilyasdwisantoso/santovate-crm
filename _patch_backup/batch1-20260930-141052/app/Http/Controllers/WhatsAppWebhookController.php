<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppMessage;
use App\Services\ProspectStageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class WhatsAppWebhookController extends Controller {
    public function verify(Request $request): Response {
        if ($request->query('hub_mode')==='subscribe' && hash_equals((string)config('santovate.whatsapp.verify_token'),(string)$request->query('hub_verify_token'))) return response((string)$request->query('hub_challenge'),200);
        return response('Forbidden',403);
    }
    public function receive(Request $request, ProspectStageService $stages): Response {
        $raw=$request->getContent(); $secret=(string)config('santovate.whatsapp.app_secret');
        if ($secret !== '') {
            $sig=(string)$request->header('X-Hub-Signature-256'); $expected='sha256='.hash_hmac('sha256',$raw,$secret);
            if (!$sig || !hash_equals($expected,$sig)) return response('Invalid signature',401);
        }
        $payload=$request->json()->all();
        foreach (($payload['entry'] ?? []) as $entry) foreach (($entry['changes'] ?? []) as $change) {
            $value=$change['value'] ?? []; $phoneNumberId=data_get($value,'metadata.phone_number_id');
            $channel=WhatsAppChannel::where('phone_number_id',$phoneNumberId)->first(); if (!$channel) continue;
            foreach (($value['statuses'] ?? []) as $status) $this->status($channel->organization_id,$status);
            foreach (($value['messages'] ?? []) as $message) $this->incoming($channel->organization_id,$message,$value,$stages);
        }
        return response('EVENT_RECEIVED',200);
    }
    private function status(int $orgId,array $status): void {
        $id=$status['id'] ?? null; if (!$id) return;
        $message=WhatsAppMessage::where('organization_id',$orgId)->where('provider_message_id',$id)->first(); if (!$message) return;
        $new=(string)($status['status'] ?? ''); $changes=['provider_payload'=>$status];
        if ($new==='sent' && !$message->sent_at) $changes['sent_at']=now();
        if ($new==='delivered' && !$message->delivered_at) $changes['delivered_at']=now();
        if ($new==='read' && !$message->read_at) $changes['read_at']=now();
        if ($new==='failed' && !$message->failed_at) $changes['failed_at']=now();
        $changes['status']=$new ?: $message->status; $message->update($changes);
        if ($message->prospect_id) {
            $p=Prospect::find($message->prospect_id);
            if ($p) {
                if ($new==='failed') {
                    $p->update(['whatsapp_status'=>'failed','whatsapp_last_error'=>json_encode($status['errors'] ?? [])]);
                } elseif (in_array($new,['delivered','read'],true)) {
                    $p->update(['whatsapp_status'=>'valid','whatsapp_verified_at'=>now(),'whatsapp_last_error'=>null]);
                }
            }
        }
        if ($message->campaign_recipient_id) {
            $r=WhatsAppCampaignRecipient::find($message->campaign_recipient_id); if (!$r) return; $campaign=$r->campaign;
            if ($new==='delivered' && !$r->delivered_at) { $r->update(['status'=>'delivered','delivered_at'=>now()]); $campaign->increment('delivered_count'); }
            if ($new==='read' && !$r->read_at) { $r->update(['status'=>'read','read_at'=>now()]); $campaign->increment('read_count'); }
            if ($new==='failed' && $r->status!=='failed') { $r->update(['status'=>'failed','error'=>json_encode($status['errors'] ?? [])]); $campaign->increment('failed_count'); }
        }
    }
    private function incoming(int $orgId,array $message,array $value,ProspectStageService $stages): void {
        $from=Prospect::normalizePhone($message['from'] ?? null); if (!$from) return;
        $p=Prospect::where('organization_id',$orgId)->where('phone_normalized',$from)->first();
        if (!$p) return;
        $body=data_get($message,'text.body') ?? data_get($message,'button.text') ?? '[Pesan WhatsApp]';
        WhatsAppMessage::updateOrCreate(['provider_message_id'=>$message['id'] ?? null],[
            'organization_id'=>$orgId,'prospect_id'=>$p->id,'direction'=>'inbound','type'=>$message['type'] ?? 'text','status'=>'received','from_phone'=>$from,'body'=>$body,'provider_payload'=>$message,
        ]);
        $now=now(); $p->update(['whatsapp_status'=>'valid','whatsapp_id'=>$message['from'] ?? $from,'whatsapp_verified_at'=>$now,'last_customer_reply_at'=>$now,'last_contact_at'=>$now,'replied_at'=>$p->replied_at ?: $now,'follow_up_snoozed_until'=>null]);
        ProspectActivity::create(['prospect_id'=>$p->id,'user_id'=>null,'type'=>'customer_reply','title'=>'Balasan WhatsApp diterima otomatis','description'=>$body,'occurred_at'=>$now]);
        if (in_array($p->status,['baru','diriset','dihubungi'],true)) {
            $actor=$p->assignedUser ?: $p->creator ?: $p->organization?->users()->where('role','admin')->first();
            if ($actor) $stages->transition($p,'membalas',$actor,'WhatsApp webhook');
        }
    }
}
