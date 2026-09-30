<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'organization_id','commercial_invoice_id','deal_id','deal_payment_schedule_id',
        'uploaded_by','verified_by','rejected_by','provider','reference_id','provider_transaction_id',
        'status','amount','payment_method','payment_channel','bank_name','sender_name','transfer_date',
        'proof_path','checkout_url','paid_at','verified_at','rejected_at','rejection_reason',
        'failure_reason','provider_payload',
    ];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return [
            'amount'=>'decimal:2','transfer_date'=>'date','paid_at'=>'datetime','verified_at'=>'datetime',
            'rejected_at'=>'datetime','provider_payload'=>'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function paymentSchedule(): BelongsTo { return $this->belongsTo(DealPaymentSchedule::class, 'deal_payment_schedule_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function rejector(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); }

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_path ? route('finance.payments.proof', ['payment'=>$this->getKey()], false) : null;
    }
}
