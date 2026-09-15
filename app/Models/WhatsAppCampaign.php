<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class WhatsAppCampaign extends Model {
    protected $table='whatsapp_campaigns';
    protected $fillable=['organization_id','follow_up_template_id','created_by','name','status','filters','total_recipients','sent_count','delivered_count','read_count','failed_count','started_at','finished_at'];
    protected function casts(): array { return ['filters'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
    public function template(): BelongsTo { return $this->belongsTo(FollowUpTemplate::class,'follow_up_template_id'); }
    public function recipients(): HasMany { return $this->hasMany(WhatsAppCampaignRecipient::class,'whatsapp_campaign_id'); }
}
