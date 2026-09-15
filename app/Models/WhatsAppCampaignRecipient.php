<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WhatsAppCampaignRecipient extends Model {
    protected $table='whatsapp_campaign_recipients';
    protected $fillable=['whatsapp_campaign_id','prospect_id','status','provider_message_id','error','sent_at','delivered_at','read_at'];
    protected function casts(): array { return ['sent_at'=>'datetime','delivered_at'=>'datetime','read_at'=>'datetime']; }
    public function campaign(): BelongsTo { return $this->belongsTo(WhatsAppCampaign::class,'whatsapp_campaign_id'); }
    public function prospect(): BelongsTo { return $this->belongsTo(Prospect::class); }
}
