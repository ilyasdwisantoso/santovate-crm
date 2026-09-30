<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommercialInvoice;
use App\Models\Deal;
use App\Models\DealPaymentSchedule;
use App\Models\PaymentTransaction;
use App\Services\FinanceService;
use App\Services\IpaymuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function create(Request $request): Response
    {
        $this->assertManager($request);

        $deals = Deal::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('status', 'won')
            ->with(['prospect','owner','paymentSchedules','invoices:id,deal_id,deal_payment_schedule_id,status,total_amount'])
            ->orderByDesc('closing_date')
            ->limit(250)
            ->get()
            ->map(fn (Deal $deal) => [
                'id'=>$deal->id,
                'deal_number'=>$deal->deal_number,
                'client'=>$deal->prospect?->company_name,
                'owner'=>$deal->owner?->name,
                'actual_deal_value'=>(float) $deal->actual_deal_value,
                'allocated_invoice_amount'=>(float) $deal->invoices->where('status', '!=', 'void')->sum('total_amount'),
                'remaining_invoice_amount'=>max(0, (float) $deal->actual_deal_value - (float) $deal->invoices->where('status', '!=', 'void')->sum('total_amount')),
                'schedules'=>$deal->paymentSchedules->map(fn ($schedule) => [
                    'id'=>$schedule->id,
                    'label'=>$schedule->label,
                    'amount'=>(float) $schedule->amount,
                    'allocated_invoice_amount'=>(float) $deal->invoices->where('status', '!=', 'void')->where('deal_payment_schedule_id', $schedule->id)->sum('total_amount'),
                    'remaining_invoice_amount'=>max(0, (float) $schedule->amount - (float) $deal->invoices->where('status', '!=', 'void')->where('deal_payment_schedule_id', $schedule->id)->sum('total_amount')),
                    'status'=>$schedule->status,
                    'due_date'=>$schedule->due_date?->format('Y-m-d'),
                ])->values(),
            ])
            ->values();

        return Inertia::render('Finance/InvoiceForm', [
            'deals'=>$deals,
            'defaults'=>[
                'deal_id'=>$request->integer('deal') ?: '',
                'payment_schedule_id'=>$request->integer('schedule') ?: '',
                'issue_date'=>now()->format('Y-m-d'),
                'due_date'=>now()->addDays(14)->format('Y-m-d'),
                'subtotal'=>'',
                'tax_amount'=>0,
                'status'=>'draft',
                'terms'=>'',
                'notes'=>'',
            ],
        ]);
    }

    public function store(Request $request, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);

        $data = $request->validate([
            'deal_id'=>['required','integer'],
            'payment_schedule_id'=>['nullable','integer'],
            'issue_date'=>['required','date'],
            'due_date'=>['nullable','date','after_or_equal:issue_date'],
            'subtotal'=>['required','numeric','min:1'],
            'tax_amount'=>['nullable','numeric','min:0'],
            'status'=>['required','in:draft,issued'],
            'terms'=>['nullable','string','max:10000'],
            'notes'=>['nullable','string','max:10000'],
        ]);

        $deal = Deal::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('status', 'won')
            ->findOrFail($data['deal_id']);

        $schedule = null;
        if (!empty($data['payment_schedule_id'])) {
            $schedule = DealPaymentSchedule::query()
                ->where('deal_id', $deal->id)
                ->findOrFail($data['payment_schedule_id']);
        }

        $invoice = DB::transaction(function () use ($request, $deal, $schedule, $data, $finance) {
            $subtotal = round((float) $data['subtotal'], 2);
            $tax = round((float) ($data['tax_amount'] ?? 0), 2);
            $total = round($subtotal + $tax, 2);
            $status = $data['status'];

            $finance->assertInvoiceAllocation($deal, $schedule, $total);

            return CommercialInvoice::create([
                'organization_id'=>$request->user()->organization_id,
                'deal_id'=>$deal->id,
                'deal_payment_schedule_id'=>$schedule?->id,
                'issued_by'=>$status === 'issued' ? $request->user()->id : null,
                'invoice_number'=>$this->nextNumber((int) $request->user()->organization_id),
                'status'=>$status,
                'currency'=>'IDR',
                'issue_date'=>$data['issue_date'],
                'due_date'=>$data['due_date'] ?? null,
                'subtotal'=>$subtotal,
                'tax_amount'=>$tax,
                'total_amount'=>$total,
                'terms'=>$data['terms'] ?? null,
                'notes'=>$data['notes'] ?? null,
                'issued_at'=>$status === 'issued' ? now() : null,
            ]);
        });

        $finance->syncInvoice($invoice);

        return redirect()->route('finance.invoices.show', $invoice)
            ->with('success', 'Invoice berhasil dibuat.');
    }

    public function show(Request $request, CommercialInvoice $invoice, FinanceService $finance): Response
    {
        $invoice = $this->visible($request, $invoice);
        $invoice = $finance->syncInvoice($invoice);

        $invoice->load([
            'organization','deal.prospect','deal.owner','paymentSchedule','issuer',
            'transactions.uploader','transactions.verifier','transactions.refunds.processor',
            'refunds.processor',
        ]);

        return Inertia::render('Finance/InvoiceShow', [
            'invoice'=>$this->payload($invoice),
            'canManage'=>$request->user()->canManageFinance(),
            'ipaymuConfigured'=>app(IpaymuService::class)->configured(),
        ]);
    }

    public function print(Request $request, CommercialInvoice $invoice, FinanceService $finance): Response
    {
        $invoice = $this->visible($request, $invoice);
        $invoice = $finance->syncInvoice($invoice);
        $invoice->load(['organization','deal.prospect','deal.owner','paymentSchedule']);

        return Inertia::render('Finance/InvoicePrint', [
            'invoice'=>$this->payload($invoice),
        ]);
    }

    public function issue(Request $request, CommercialInvoice $invoice, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $invoice = $this->organizationInvoice($request, $invoice);

        abort_unless($invoice->status === 'draft', 422, 'Hanya invoice Draft yang dapat diterbitkan.');

        DB::transaction(function () use ($request, $invoice, $finance) {
            $locked = CommercialInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($locked->status === 'draft', 422, 'Hanya invoice Draft yang dapat diterbitkan.');

            $deal = $locked->deal()->firstOrFail();
            $schedule = $locked->paymentSchedule()->first();
            $finance->assertInvoiceAllocation($deal, $schedule, (float) $locked->total_amount, $locked->id);

            $locked->update([
                'status'=>'issued',
                'issued_by'=>$request->user()->id,
                'issued_at'=>now(),
            ]);
        });

        $finance->syncInvoice($invoice->fresh());

        return back()->with('success', 'Invoice berhasil diterbitkan.');
    }

    public function void(Request $request, CommercialInvoice $invoice, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $invoice = $this->organizationInvoice($request, $invoice);
        $data = $request->validate(['reason'=>['required','string','max:2000']]);

        abort_if(
            $invoice->transactions()->whereIn('status', ['paid','refunded'])->exists(),
            422,
            'Invoice yang memiliki pembayaran terverifikasi tidak dapat di-void.'
        );

        $invoice->update([
            'status'=>'void',
            'voided_by'=>$request->user()->id,
            'voided_at'=>now(),
            'void_reason'=>$data['reason'],
        ]);

        $invoice->transactions()
            ->whereIn('status', ['pending_verification','waiting_payment'])
            ->update(['status'=>'cancelled','updated_at'=>now()]);

        if ($invoice->deal_payment_schedule_id) {
            $finance->syncSchedule($invoice->paymentSchedule()->first());
        }
        $finance->syncCommission($invoice->deal()->first());

        return redirect()->route('finance.index')->with('success', 'Invoice di-void.');
    }

    public function storeManualPayment(Request $request, CommercialInvoice $invoice, FinanceService $finance): RedirectResponse
    {
        $invoice = $this->visible($request, $invoice);
        $invoice = $finance->syncInvoice($invoice);
        abort_unless($invoice->isPayable(), 422, 'Invoice tidak dapat menerima pembayaran.');

        $data = $request->validate([
            'amount'=>['required','numeric','min:1','max:'.$invoice->outstanding_amount],
            'transfer_date'=>['required','date'],
            'bank_name'=>['required','string','max:255'],
            'sender_name'=>['required','string','max:255'],
            'proof'=>['required','file','max:10240','mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('proof');
        $path = $file->store(
            "organizations/{$request->user()->organization_id}/finance/invoices/{$invoice->id}",
            'local'
        );

        PaymentTransaction::create([
            'organization_id'=>$request->user()->organization_id,
            'commercial_invoice_id'=>$invoice->id,
            'deal_id'=>$invoice->deal_id,
            'deal_payment_schedule_id'=>$invoice->deal_payment_schedule_id,
            'uploaded_by'=>$request->user()->id,
            'provider'=>'manual',
            'reference_id'=>$this->paymentReference('MNL'),
            'status'=>'pending_verification',
            'amount'=>$data['amount'],
            'payment_method'=>'manual_transfer',
            'bank_name'=>$data['bank_name'],
            'sender_name'=>$data['sender_name'],
            'transfer_date'=>$data['transfer_date'],
            'proof_path'=>$path,
        ]);

        return back()->with('success', 'Bukti transfer tersimpan dan menunggu verifikasi Finance/Admin.');
    }

    public function createGatewayPayment(
        Request $request,
        CommercialInvoice $invoice,
        FinanceService $finance,
        IpaymuService $ipaymu
    ): RedirectResponse {
        $invoice = $this->visible($request, $invoice);
        $invoice = $finance->syncInvoice($invoice);
        abort_unless($invoice->isPayable(), 422, 'Invoice tidak dapat menerima pembayaran.');

        $data = $request->validate([
            'payment_method'=>['required','string','max:30'],
            'payment_channel'=>['required','string','max:30'],
        ]);

        $invoice->loadMissing('deal.prospect');
        $prospect = $invoice->deal?->prospect;

        $transaction = PaymentTransaction::create([
            'organization_id'=>$request->user()->organization_id,
            'commercial_invoice_id'=>$invoice->id,
            'deal_id'=>$invoice->deal_id,
            'deal_payment_schedule_id'=>$invoice->deal_payment_schedule_id,
            'uploaded_by'=>$request->user()->id,
            'provider'=>'ipaymu',
            'reference_id'=>$this->paymentReference('SVI'),
            'status'=>'waiting_payment',
            'amount'=>$invoice->outstanding_amount,
            'payment_method'=>$data['payment_method'],
            'payment_channel'=>$data['payment_channel'],
        ]);

        try {
            $result = $ipaymu->createCommercialCheckout($transaction, [
                'name'=>$prospect?->contact_name ?: $prospect?->company_name ?: 'Customer',
                'email'=>$prospect?->email ?: $request->user()->email,
                'phone'=>$prospect?->phone ?: '',
                'payment_method'=>$data['payment_method'],
                'payment_channel'=>$data['payment_channel'],
            ]);

            $checkout = data_get($result, 'Data.Url') ?? data_get($result, 'Data.url') ?? data_get($result, 'Url');

            $transaction->update([
                'provider_transaction_id'=>(string) (data_get($result, 'Data.SessionId') ?? data_get($result, 'Data.TransactionId') ?? ''),
                'checkout_url'=>$checkout,
                'provider_payload'=>$result,
            ]);

            abort_if(!$checkout, 502, 'iPaymu tidak mengembalikan checkout URL.');

            return back()->with('success', 'Payment link iPaymu berhasil dibuat.');
        } catch (\Throwable $e) {
            report($e);
            $transaction->update(['status'=>'failed','failure_reason'=>$e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    private function payload(CommercialInvoice $invoice): array
    {
        return [
            'id'=>$invoice->id,
            'invoice_number'=>$invoice->invoice_number,
            'status'=>$invoice->status,
            'currency'=>$invoice->currency,
            'issue_date'=>$invoice->issue_date?->format('Y-m-d'),
            'due_date'=>$invoice->due_date?->format('Y-m-d'),
            'subtotal'=>(float) $invoice->subtotal,
            'tax_amount'=>(float) $invoice->tax_amount,
            'total_amount'=>(float) $invoice->total_amount,
            'paid_amount'=>(float) $invoice->paid_amount,
            'refunded_amount'=>(float) $invoice->refunded_amount,
            'net_paid_amount'=>$invoice->net_paid_amount,
            'outstanding_amount'=>$invoice->outstanding_amount,
            'terms'=>$invoice->terms,
            'notes'=>$invoice->notes,
            'issued_at'=>$invoice->issued_at?->toIso8601String(),
            'void_reason'=>$invoice->void_reason,
            'organization'=>$invoice->organization ? [
                'id'=>$invoice->organization->id,
                'name'=>$invoice->organization->name,
                'invoice_brand_name'=>data_get($invoice->organization->settings, 'invoice_brand_name'),
                'contact_email'=>$invoice->organization->contact_email,
                'contact_phone'=>$invoice->organization->contact_phone,
                'address'=>$invoice->organization->address,
                'invoice_footer'=>data_get($invoice->organization->settings, 'invoice_footer'),
            ] : null,
            'deal'=>[
                'id'=>$invoice->deal?->id,
                'deal_number'=>$invoice->deal?->deal_number,
                'actual_deal_value'=>(float) ($invoice->deal?->actual_deal_value ?? 0),
                'commissionable_value'=>(float) ($invoice->deal?->commissionable_value ?? 0),
                'client'=>$invoice->deal?->prospect?->company_name,
                'contact_name'=>$invoice->deal?->prospect?->contact_name,
                'email'=>$invoice->deal?->prospect?->email,
                'phone'=>$invoice->deal?->prospect?->phone,
                'owner'=>$invoice->deal?->owner?->name,
            ],
            'schedule'=>$invoice->paymentSchedule ? [
                'id'=>$invoice->paymentSchedule->id,
                'label'=>$invoice->paymentSchedule->label,
                'amount'=>(float) $invoice->paymentSchedule->amount,
                'status'=>$invoice->paymentSchedule->status,
            ] : null,
            'issuer'=>$invoice->issuer?->name,
            'transactions'=>$invoice->relationLoaded('transactions') ? $invoice->transactions->map(fn ($payment) => [
                'id'=>$payment->id,
                'provider'=>$payment->provider,
                'reference_id'=>$payment->reference_id,
                'provider_transaction_id'=>$payment->provider_transaction_id,
                'status'=>$payment->status,
                'amount'=>(float) $payment->amount,
                'payment_method'=>$payment->payment_method,
                'payment_channel'=>$payment->payment_channel,
                'bank_name'=>$payment->bank_name,
                'sender_name'=>$payment->sender_name,
                'transfer_date'=>$payment->transfer_date?->format('Y-m-d'),
                'proof_url'=>$payment->proof_url,
                'checkout_url'=>$payment->checkout_url,
                'paid_at'=>$payment->paid_at?->toIso8601String(),
                'verified_at'=>$payment->verified_at?->toIso8601String(),
                'verified_by'=>$payment->verifier?->name,
                'uploaded_by'=>$payment->uploader?->name,
                'rejection_reason'=>$payment->rejection_reason,
                'failure_reason'=>$payment->failure_reason,
                'refunded_amount'=>(float) $payment->refunds->where('status', 'processed')->sum('amount'),
                'created_at'=>$payment->created_at?->toIso8601String(),
            ])->values() : [],
            'refunds'=>$invoice->relationLoaded('refunds') ? $invoice->refunds->map(fn ($refund) => [
                'id'=>$refund->id,
                'amount'=>(float) $refund->amount,
                'reason'=>$refund->reason,
                'reference_number'=>$refund->reference_number,
                'refunded_at'=>$refund->refunded_at?->toIso8601String(),
                'processed_by'=>$refund->processor?->name,
            ])->values() : [],
        ];
    }

    private function visible(Request $request, CommercialInvoice $invoice): CommercialInvoice
    {
        abort_unless(
            CommercialInvoice::query()->visibleTo($request->user())->whereKey($invoice->id)->exists(),
            403
        );
        return $invoice;
    }

    private function organizationInvoice(Request $request, CommercialInvoice $invoice): CommercialInvoice
    {
        abort_unless((int) $invoice->organization_id === (int) $request->user()->organization_id, 403);
        return $invoice;
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()->canManageFinance(), 403);
    }

    private function nextNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $last = CommercialInvoice::query()
            ->where('organization_id', $organizationId)
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('invoice_number');

        $sequence = $last ? ((int) substr($last, -6)) + 1 : 1;
        return sprintf('INV-%s-%06d', $year, $sequence);
    }

    private function paymentReference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
    }
}
