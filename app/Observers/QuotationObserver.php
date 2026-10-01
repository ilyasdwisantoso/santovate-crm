<?php

namespace App\Observers;

use App\Models\Quotation;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use App\Services\ApprovalService;

class QuotationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Quotation $quotation): void
    {
        app(ApprovalService::class)->syncQuotation($quotation);
    }

    public function updated(Quotation $quotation): void
    {
        if ($quotation->wasChanged(['status','approval_status','requires_approval','grand_total','discount_amount','payment_terms','pricing_type','revision_number'])) {
            app(ApprovalService::class)->syncQuotation($quotation);
        }
    }
}
