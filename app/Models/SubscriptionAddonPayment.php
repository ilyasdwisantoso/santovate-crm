<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddonPayment extends Model
{
    protected $fillable = [
        'organization_id','subscription_addon_order_id','provider','reference_id','provider_transaction_id','status','amount',
        'payment_method','payment_channel','checkout_url','paid_at','failure_reason','provider_payload',
    ];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return ['amount'=>'integer','paid_at'=>'datetime','provider_payload'=>'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function order(): BelongsTo { return $this->belongsTo(SubscriptionAddonOrder::class, 'subscription_addon_order_id'); }
}
