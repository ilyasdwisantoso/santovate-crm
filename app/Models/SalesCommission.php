<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCommission extends Model
{
    protected $fillable = [
        'organization_id','deal_id','user_id','rate','commissionable_value','potential_amount',
        'earned_amount','approved_amount','paid_amount','status','approved_by','approved_at','last_recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'rate'=>'decimal:4','commissionable_value'=>'decimal:2','potential_amount'=>'decimal:2',
            'earned_amount'=>'decimal:2','approved_amount'=>'decimal:2','paid_amount'=>'decimal:2',
            'approved_at'=>'datetime','last_recalculated_at'=>'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function ledgerEntries(): HasMany { return $this->hasMany(CommissionLedgerEntry::class)->orderByDesc('occurred_at'); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('organization_id', $user->organization_id);
        if ($user->canManageFinance()) return $query;
        return $query->where('user_id', $user->id);
    }

    public function getPayableAmountAttribute(): float
    {
        return max(0, (float) $this->approved_amount - (float) $this->paid_amount);
    }

    public function getAdjustmentDueAttribute(): float
    {
        return max(0, (float) $this->paid_amount - (float) $this->earned_amount);
    }
}
