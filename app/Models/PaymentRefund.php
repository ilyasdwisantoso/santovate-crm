<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'organization_id','payment_transaction_id','commercial_invoice_id','deal_id',
        'requested_by','processed_by','status','amount','reference_number','reason','refunded_at',
    ];

    protected function casts(): array
    {
        return ['amount'=>'decimal:2','refunded_at'=>'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
