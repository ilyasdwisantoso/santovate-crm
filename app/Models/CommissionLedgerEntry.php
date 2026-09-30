<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLedgerEntry extends Model
{
    protected $fillable = [
        'organization_id','sales_commission_id','deal_id','commercial_invoice_id',
        'payment_transaction_id','payment_refund_id','created_by','entry_type','amount',
        'reference_number','notes','occurred_at',
    ];

    protected function casts(): array
    {
        return ['amount'=>'decimal:2','occurred_at'=>'datetime'];
    }

    public function commission(): BelongsTo { return $this->belongsTo(SalesCommission::class, 'sales_commission_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id'); }
    public function refund(): BelongsTo { return $this->belongsTo(PaymentRefund::class, 'payment_refund_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
