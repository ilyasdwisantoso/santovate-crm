<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialInvoice extends Model
{
    protected $fillable = [
        'organization_id','deal_id','deal_payment_schedule_id','issued_by','voided_by',
        'invoice_number','status','currency','issue_date','due_date','subtotal','tax_amount',
        'total_amount','paid_amount','refunded_amount','terms','notes','issued_at','voided_at','void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'=>'date','due_date'=>'date','subtotal'=>'decimal:2','tax_amount'=>'decimal:2',
            'total_amount'=>'decimal:2','paid_amount'=>'decimal:2','refunded_amount'=>'decimal:2',
            'issued_at'=>'datetime','voided_at'=>'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function paymentSchedule(): BelongsTo { return $this->belongsTo(DealPaymentSchedule::class, 'deal_payment_schedule_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function transactions(): HasMany { return $this->hasMany(PaymentTransaction::class); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('organization_id', $user->organization_id);

        if ($user->canManageFinance()) {
            return $query;
        }

        return $query
            ->where('status', '!=', 'draft')
            ->whereHas('deal', fn (Builder $deal) => $deal->where('owner_id', $user->id));
    }

    public function getNetPaidAmountAttribute(): float
    {
        return max(0, (float) $this->paid_amount - (float) $this->refunded_amount);
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->net_paid_amount);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['issued','partially_paid','overdue'], true) && $this->outstanding_amount > 0.01;
    }
}
