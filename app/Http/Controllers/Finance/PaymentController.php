<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommercialInvoice;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends Controller
{
    public function proof(Request $request, PaymentTransaction $payment): BinaryFileResponse
    {
        $this->assertOrganization($request, $payment);

        if (!$request->user()->canManageFinance()) {
            abort_unless(
                CommercialInvoice::query()
                    ->visibleTo($request->user())
                    ->whereKey($payment->commercial_invoice_id)
                    ->exists(),
                403
            );
        }

        abort_unless($payment->proof_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($payment->proof_path), 404);

        $mime = $disk->mimeType($payment->proof_path) ?: 'application/octet-stream';

        return response()->file($disk->path($payment->proof_path), [
            'Content-Type'=>$mime,
            'Cache-Control'=>'private, no-store, max-age=0',
            'Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function verify(Request $request, PaymentTransaction $payment, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $this->assertOrganization($request, $payment);

        DB::transaction(function () use ($request, $payment, $finance) {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($locked->status === 'pending_verification', 422, 'Payment tidak sedang menunggu verifikasi.');

            $invoice = $locked->invoice()->lockForUpdate()->firstOrFail();
            $invoice = $finance->syncInvoice($invoice);

            abort_if($invoice->status === 'void', 422, 'Payment untuk invoice Void tidak dapat diverifikasi sebagai Paid.');
            abort_if((float) $locked->amount > $invoice->outstanding_amount + 0.01, 422, 'Nominal payment melebihi outstanding invoice saat ini.');

            $locked->update([
                'status'=>'paid',
                'verified_by'=>$request->user()->id,
                'verified_at'=>now(),
                'paid_at'=>$locked->paid_at ?: now(),
                'rejection_reason'=>null,
                'rejected_by'=>null,
                'rejected_at'=>null,
            ]);

            $finance->syncInvoice($invoice, [
                'commercial_invoice_id'=>$invoice->id,
                'payment_transaction_id'=>$locked->id,
                'user_id'=>$request->user()->id,
                'reference_number'=>$locked->reference_id,
                'notes'=>'Verified client payment.',
            ]);
        });

        return back()->with('success', 'Payment terverifikasi. Invoice dan komisi telah dihitung ulang.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $this->assertManager($request);
        $this->assertOrganization($request, $payment);

        $data = $request->validate(['reason'=>['required','string','max:2000']]);
        abort_unless($payment->status === 'pending_verification', 422, 'Payment tidak sedang menunggu verifikasi.');

        $payment->update([
            'status'=>'rejected',
            'rejected_by'=>$request->user()->id,
            'rejected_at'=>now(),
            'rejection_reason'=>$data['reason'],
        ]);

        return back()->with('success', 'Bukti pembayaran ditolak.');
    }

    public function refund(Request $request, PaymentTransaction $payment, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $this->assertOrganization($request, $payment);

        $data = $request->validate([
            'amount'=>['required','numeric','min:1'],
            'reason'=>['required','string','max:2000'],
            'reference_number'=>['nullable','string','max:255'],
            'refunded_at'=>['required','date'],
        ]);

        DB::transaction(function () use ($request, $payment, $data, $finance) {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless(in_array($locked->status, ['paid','refunded'], true), 422, 'Hanya payment terverifikasi yang dapat direfund.');

            $processed = (float) $locked->refunds()->where('status', 'processed')->sum('amount');
            $remaining = max(0, (float) $locked->amount - $processed);
            abort_if((float) $data['amount'] > $remaining + 0.01, 422, 'Nominal refund melebihi saldo payment yang dapat direfund.');

            $refund = PaymentRefund::create([
                'organization_id'=>$locked->organization_id,
                'payment_transaction_id'=>$locked->id,
                'commercial_invoice_id'=>$locked->commercial_invoice_id,
                'deal_id'=>$locked->deal_id,
                'requested_by'=>$request->user()->id,
                'processed_by'=>$request->user()->id,
                'status'=>'processed',
                'amount'=>$data['amount'],
                'reference_number'=>$data['reference_number'] ?? null,
                'reason'=>$data['reason'],
                'refunded_at'=>$data['refunded_at'],
            ]);

            $newProcessed = $processed + (float) $data['amount'];
            if ($newProcessed + 0.01 >= (float) $locked->amount) {
                $locked->update(['status'=>'refunded']);
            }

            $invoice = $locked->invoice()->firstOrFail();
            $finance->syncInvoice($invoice, [
                'commercial_invoice_id'=>$invoice->id,
                'payment_transaction_id'=>$locked->id,
                'payment_refund_id'=>$refund->id,
                'user_id'=>$request->user()->id,
                'reference_number'=>$refund->reference_number,
                'notes'=>'Refund adjustment: '.$refund->reason,
            ]);
        });

        return back()->with('success', 'Refund tercatat sebagai adjustment. Revenue dan komisi telah dihitung ulang.');
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()->canManageFinance(), 403);
    }

    private function assertOrganization(Request $request, PaymentTransaction $payment): void
    {
        abort_unless((int) $payment->organization_id === (int) $request->user()->organization_id, 403);
    }
}
