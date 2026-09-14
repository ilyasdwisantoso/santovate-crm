<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $fillable = ['name','slug','business_configuration_id','status','contact_email','contact_phone','address','settings'];
    protected function casts(): array { return ['settings'=>'array']; }
    public function businessConfiguration(): BelongsTo { return $this->belongsTo(BusinessConfiguration::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function prospects(): HasMany { return $this->hasMany(Prospect::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function whatsappChannel(): HasOne { return $this->hasOne(WhatsAppChannel::class); }
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->with(['plan','businessConfiguration'])
            ->where('status','active')->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at','>',now());
            })->latest('activated_at')->first();
    }
}
