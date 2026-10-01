<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionAddonOrder extends Model
{
    protected $fillable = [
        'organization_id','subscription_id','subscription_addon_id','requested_by','billing_cycle','units',
        'resource_key','resource_quantity','unit_price','total_amount','status','starts_at','ends_at','activated_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'units'=>'integer','resource_quantity'=>'integer','unit_price'=>'integer','total_amount'=>'integer',
            'starts_at'=>'datetime','ends_at'=>'datetime','activated_at'=>'datetime','metadata'=>'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function addon(): BelongsTo { return $this->belongsTo(SubscriptionAddon::class, 'subscription_addon_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function payment(): HasOne { return $this->hasOne(SubscriptionAddonPayment::class); }
}
