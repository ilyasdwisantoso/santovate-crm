<?php

namespace App\Services;

use App\Models\FollowUpTemplate;
use App\Models\Prospect;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    public function __construct(private readonly FollowUpService $followUps) {}

    public function send(Prospect $prospect, User $user, FollowUpTemplate $template, ?string $editedMessage=null, ?int $campaignRecipientId=null): WhatsAppMessage
    {
        abort_unless($prospect->organization_id === $user->organization_id,403);
        $prospect->loadMissing(['organization.whatsappChannel','products']);
        $channel=$prospect->organization?->whatsappChannel;
        if (!$channel?->ready()) throw new RuntimeException('WhatsApp Cloud API belum siap untuk workspace ini.');
        $phone=$prospect->phone_normalized ?: Prospect::normalizePhone($prospect->phone);
        if (!$phone) throw new RuntimeException('Nomor WhatsApp prospect belum tersedia.');

        $insideWindow=$prospect->last_customer_reply_at && $prospect->last_customer_reply_at->gt(now()->subHours(24));
        if (!$insideWindow && (!$prospect->whatsapp_opt_in_at || $prospect->whatsapp_opt_out_at)) {
            throw new RuntimeException('Kontak belum memiliki WhatsApp opt-in aktif untuk pesan business-initiated.');
        }

        $body=$editedMessage ?: $this->followUps->renderTemplate($template,$prospect,$user);
        if ($insideWindow && blank($template->meta_template_name)) {
            $payload=['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'text','text'=>['preview_url'=>false,'body'=>$body]];
            $type='text';
        } else {
            if (blank($template->meta_template_name) || strtolower((string)$template->meta_status) !== 'approved') {
                throw new RuntimeException('Pesan di luar 24 jam membutuhkan approved Meta template.');
            }
            $components=[];
            if ($template->header_type==='image' && filled($template->image_url)) {
                $components[]=['type'=>'header','parameters'=>[['type'=>'image','image'=>['link'=>$template->image_url]]]];
            }
            $params=[];
            foreach ($template->body_parameters ?? [] as $key) {
                $params[]=['type'=>'text','text'=>$this->parameterValue((string)$key,$prospect,$user)];
            }
            if ($params) $components[]=['type'=>'body','parameters'=>$params];
            $payload=['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'template','template'=>[
                'name'=>$template->meta_template_name,'language'=>['code'=>$template->meta_language ?: 'id'],'components'=>$components,
            ]];
            $type='template';
        }

        $url='https://graph.facebook.com/'.config('santovate.whatsapp.graph_version').'/'.$channel->phone_number_id.'/messages';
        $response=Http::withToken($channel->access_token)->acceptJson()->post($url,$payload);
        if (!$response->successful()) {
            $prospect->update(['whatsapp_status'=>'failed','whatsapp_last_error'=>$response->body()]);
            throw new RuntimeException('WhatsApp API gagal: '.$response->status().' '.$response->body());
        }
        $data=$response->json(); $messageId=data_get($data,'messages.0.id');
        $message=WhatsAppMessage::create([
            'organization_id'=>$prospect->organization_id,'prospect_id'=>$prospect->id,'user_id'=>$user->id,
            'follow_up_template_id'=>$template->id,'campaign_recipient_id'=>$campaignRecipientId,'direction'=>'outbound','type'=>$type,
            'status'=>'sent','to_phone'=>$phone,'provider_message_id'=>$messageId,'body'=>$body,'media_url'=>$template->image_url,
            'provider_payload'=>$data,'sent_at'=>now(),
        ]);
        $prospect->update([
            'phone_normalized'=>$phone,'whatsapp_status'=>'valid','whatsapp_id'=>$phone,'whatsapp_verified_at'=>now(),
            'last_outbound_at'=>now(),'last_contact_at'=>now(),'contacted_at'=>$prospect->contacted_at ?: now(),
            'follow_up_snoozed_until'=>null,'follow_up_count'=>(int)$prospect->follow_up_count+1,'last_follow_up_message'=>$body,
        ]);
        return $message;
    }

    private function parameterValue(string $key, Prospect $p, User $u): string
    {
        $product=$p->primaryProduct();
        return match($key) {
            'contact_name'=>$p->contact_name ?: 'Bapak/Ibu','company_name'=>$p->company_name,'business_name'=>$p->organization?->name ?: 'Santovate',
            'ae_name'=>$u->name,'ae_first_name'=>explode(' ',trim($u->name))[0] ?? $u->name,'ae_phone'=>$u->phone ?: '',
            'service'=>$p->service ?: 'solusi CRM','product_name'=>$product?->name ?: ($p->service ?: 'produk'),
            'product_variant'=>$product?->variant ?: '','product_price'=>$product?'Rp '.number_format((int)$product->price,0,',','.'):'',
            default=>'',
        };
    }
}
