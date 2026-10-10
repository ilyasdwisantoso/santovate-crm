<?php

namespace App\Http\Controllers;

use App\Models\CommercialInvoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\FinanceService;
use App\Services\IpaymuCallbackGuard;
use App\Services\IpaymuCallbackVerifier;
use App\Services\IpaymuService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IpaymuWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        IpaymuService $ipaymu,
        SubscriptionService $subscriptions,
        FinanceService $finance,
        IpaymuCallbackVerifier $verifier,
        IpaymuCallbackGuard $guard
    ): JsonResponse {
        if (!$verifier->validate($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $reference = $guard->reference($payload);
        if ($reference === '') return $this->ack();

        // Per-reference payment row locks serialize duplicate / simultaneous callbacks.
        // All payment + subscription/finance updates commit as one transaction.
        return DB::transaction(function () use ($payload, $reference, $ipaymu, $subscriptions, $finance, $guard) {
            $commercial = PaymentTransaction::query()
                ->where('reference_id', $reference)->lockForUpdate()->first();
            if ($commercial) {
                return $this->commercial($commercial, $payload, $reference, $ipaymu, $finance, $guard);
            }

            $payment = Payment::query()
                ->where('reference_id', $reference)->lockForUpdate()->first();
            if ($payment) {
                return $this->subscription($payment, $payload, $reference, $ipaymu, $subscriptions, $guard);
            }

            return $this->ack(); // Do not reveal whether a reference exists.
        }, 3);
    }

    private function commercial(
        PaymentTransaction $payment,
        array $payload,
        string $reference,
        IpaymuService $ipaymu,
        FinanceService $finance,
        IpaymuCallbackGuard $guard
    ): JsonResponse {
        if (in_array($payment->status, ['paid', 'refunded'], true)) return $this->ack();

        $data = [
            'provider_payload' => $payload,
            'provider_transaction_id' => (string) ($payload['trx_id'] ?? $payload['transaction_id'] ?? $payment->provider_transaction_id),
        ];

        if (!$guard->isPaid($payload)) {
            if ($payment->status === 'pending' && $failed = $guard->failedStatus($payload)) {
                $data['status'] = $failed;
            }
            $payment->update($data);
            return $this->ack();
        }

        $invoice = $payment->commercial_invoice_id
            ? CommercialInvoice::query()->whereKey($payment->commercial_invoice_id)->lockForUpdate()->first()
            : null;
        if (!$invoice || !$guard->organizationMatches((int) $payment->organization_id, $invoice ? (int) $invoice->organization_id : null)) {
            return $this->reviewCommercial($payment, $data, $reference, 'Invoice tenant mismatch or missing invoice.');
        }

        if (in_array($invoice->status, ['draft', 'void'], true)) {
            return $this->reviewCommercial($payment, $data, $reference, 'Invoice is draft or void.');
        }

        $issue = $guard->paymentIssue($payload, $reference, (float) $payment->amount, $ipaymu->callbackAmount($payload));
        if ($issue !== null) return $this->reviewCommercial($payment, $data, $reference, $issue);

        $invoice = $finance->syncInvoice($invoice);
        if ((float) $payment->amount > $invoice->outstanding_amount + 0.01) {
            return $this->reviewCommercial($payment, $data, $reference, 'Payment exceeds invoice outstanding amount.');
        }

        $payment->update($data + [
            'status' => 'paid', 'paid_at' => now(), 'verified_at' => now(), 'failure_reason' => null,
        ]);
        $finance->syncInvoice($invoice, [
            'commercial_invoice_id' => $invoice->id,
            'payment_transaction_id' => $payment->id,
            'reference_number' => $reference,
            'notes' => 'Verified automatically from signed iPaymu callback.',
        ]);
        return $this->ack();
    }

    private function subscription(
        Payment $payment,
        array $payload,
        string $reference,
        IpaymuService $ipaymu,
        SubscriptionService $subscriptions,
        IpaymuCallbackGuard $guard
    ): JsonResponse {
        if (in_array($payment->status, ['paid', 'refunded'], true)) return $this->ack();

        if (!$guard->isPaid($payload)) {
            if ($payment->status === 'pending') $payment->update(['provider_payload' => $payload]);
            return $this->ack();
        }

        $sub = $payment->subscription_id
            ? Subscription::query()->whereKey($payment->subscription_id)->lockForUpdate()->first()
            : null;
        $issue = $guard->paymentIssue($payload, $reference, (float) $payment->amount, $ipaymu->callbackAmount($payload));
        $invalidSubscription = !$sub
            || !$guard->organizationMatches((int) $payment->organization_id, $sub ? (int) $sub->organization_id : null)
            || !in_array($sub->status, ['pending', 'active'], true)
            || !$guard->storedAmountsMatch((float) $payment->amount, (float) $sub->total_amount);

        if ($issue !== null || $invalidSubscription) {
            $payment->update(['status' => 'pending_verification', 'provider_payload' => $payload]);
            Log::warning('iPaymu subscription callback awaiting review', [
                'reference' => $reference,
                'reason' => $issue ?? 'Subscription tenant mismatch or invalid lifecycle state.',
            ]);
            return $this->ack();
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => $payment->paid_at ?: now(),
            'provider_transaction_id' => (string) ($payload['trx_id'] ?? $payload['transaction_id'] ?? $payment->provider_transaction_id),
            'provider_payload' => $payload,
        ]);
        if ($sub->status === 'pending') $subscriptions->activate($sub);
        return $this->ack();
    }

    private function reviewCommercial(PaymentTransaction $payment, array $data, string $reference, string $reason): JsonResponse
    {
        $payment->update($data + [
            'status' => 'pending_verification',
            'failure_reason' => 'iPaymu callback requires finance review: '.$reason,
        ]);
        Log::warning('iPaymu commercial callback awaiting review', ['reference' => $reference, 'reason' => $reason]);
        return $this->ack();
    }

    private function ack(): JsonResponse
    {
        return response()->json(['message' => 'OK'], 200);
    }
}
