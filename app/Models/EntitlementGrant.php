<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntitlementGrant extends Model
{
    protected $fillable = [
        'organization_id','subscription_id','key','kind','operation','integer_value','boolean_value',
        'source_type','source_id','reference','status','starts_at','ends_at','metadata','created_by',
    ];

    protected function casts(): array
    {
        return [
            'integer_value'=>'integer','boolean_value'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime','metadata'=>'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status','active')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at','>',now()));
    }
}
