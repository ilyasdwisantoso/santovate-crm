<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model {
    protected $fillable=['organization_id','subscription_id','provider','reference_id','provider_transaction_id','status','amount','payment_method','payment_channel','checkout_url','paid_at','provider_payload'];
    protected $hidden=['provider_payload'];
    protected function casts(): array { return ['amount'=>'integer','paid_at'=>'datetime','provider_payload'=>'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}
