<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WhatsAppMessage extends Model {
    protected $fillable=['organization_id','prospect_id','user_id','follow_up_template_id','campaign_recipient_id','direction','type','status','to_phone','from_phone','provider_message_id','body','media_url','provider_payload','sent_at','delivered_at','read_at','failed_at'];
    protected function casts(): array { return ['provider_payload'=>'array','sent_at'=>'datetime','delivered_at'=>'datetime','read_at'=>'datetime','failed_at'=>'datetime']; }
}
