<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Subscription extends Model {
    protected $fillable=['organization_id','subscription_plan_id','business_configuration_id','billing_cycle','status','base_amount','configuration_amount','total_amount','starts_at','ends_at','activated_at','cancelled_at'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','activated_at'=>'datetime','cancelled_at'=>'datetime','base_amount'=>'integer','configuration_amount'=>'integer','total_amount'=>'integer']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id'); }
    public function businessConfiguration(): BelongsTo { return $this->belongsTo(BusinessConfiguration::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
