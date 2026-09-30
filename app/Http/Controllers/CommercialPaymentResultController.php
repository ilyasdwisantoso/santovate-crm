<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommercialPaymentResultController extends Controller
{
    public function __invoke(Request $request, string $reference): Response
    {
        $transaction = PaymentTransaction::query()
            ->where('reference_id', $reference)
            ->with(['invoice.organization'])
            ->first();

        return Inertia::render('Public/CommercialPaymentResult', [
            'payment'=>$transaction ? [
                'reference_id'=>$transaction->reference_id,
                'status'=>$transaction->status,
                'amount'=>(float) $transaction->amount,
                'invoice_number'=>$transaction->invoice?->invoice_number,
                'organization'=>$transaction->invoice?->organization?->name,
                'paid_at'=>$transaction->paid_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
