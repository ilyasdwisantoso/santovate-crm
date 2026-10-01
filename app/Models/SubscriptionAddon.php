<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionAddon extends Model
{
    protected $fillable = [
        'key','name','description','resource_key','resource_quantity','monthly_price','annual_price','is_active','sort_order','metadata',
    ];

    protected function casts(): array
    {
        return [
            'resource_quantity'=>'integer','monthly_price'=>'integer','annual_price'=>'integer',
            'is_active'=>'boolean','sort_order'=>'integer','metadata'=>'array',
        ];
    }

    public function orders(): HasMany { return $this->hasMany(SubscriptionAddonOrder::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }

    public function priceFor(string $billingCycle): int
    {
        return $billingCycle === 'annual' ? (int)$this->annual_price : (int)$this->monthly_price;
    }
}
