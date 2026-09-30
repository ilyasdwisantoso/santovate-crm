<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommercialInvoice;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $finance): Response
    {
        $user = $request->user();
        $finance->markOverdue((int) $user->organization_id);

        $query = CommercialInvoice::query()
            ->visibleTo($user)
            ->with(['deal.prospect','deal.owner','paymentSchedule']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('deal_id')) {
            $query->where('deal_id', (int) $request->deal_id);
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->q);
            $query->where(function ($invoice) use ($search) {
                $invoice->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('deal', fn ($deal) => $deal
                        ->where('deal_number', 'like', "%{$search}%")
                        ->orWhereHas('prospect', fn ($prospect) => $prospect->where('company_name', 'like', "%{$search}%")));
            });
        }

        $rows = $query->orderByDesc('issue_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $rows->through(fn (CommercialInvoice $invoice) => [
            'id'=>$invoice->id,
            'invoice_number'=>$invoice->invoice_number,
            'status'=>$invoice->status,
            'issue_date'=>$invoice->issue_date?->format('Y-m-d'),
            'due_date'=>$invoice->due_date?->format('Y-m-d'),
            'total_amount'=>(float) $invoice->total_amount,
            'paid_amount'=>(float) $invoice->paid_amount,
            'refunded_amount'=>(float) $invoice->refunded_amount,
            'outstanding_amount'=>$invoice->outstanding_amount,
            'deal'=>[
                'id'=>$invoice->deal?->id,
                'deal_number'=>$invoice->deal?->deal_number,
                'client'=>$invoice->deal?->prospect?->company_name,
                'owner'=>$invoice->deal?->owner?->name,
            ],
            'schedule'=>$invoice->paymentSchedule?->label,
        ]);

        return Inertia::render('Finance/Index', [
            'invoices'=>$rows,
            'summary'=>$finance->summaryFor($user),
            'filters'=>$request->only(['q','status','deal_id']),
            'canManage'=>$user->canManageFinance(),
        ]);
    }
}
