<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionAddonPayment;
use App\Services\IpaymuCallbackGuard;
use App\Services\IpaymuCallbackVerifier;
use App\Services\IpaymuService;
use App\Services\SubscriptionAddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IpaymuAddonWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        IpaymuService $ipaymu,
        SubscriptionAddonService $addons,
        IpaymuCallbackVerifier $verifier,
        IpaymuCallbackGuard $guard
    ): JsonResponse {
        if (!$verifier->validate($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $reference = $guard->reference($payload);
        if ($reference === '') return $this->ack();

        return DB::transaction(function () use ($payload, $reference, $ipaymu, $addons, $guard) {
            $payment = SubscriptionAddonPayment::query()
                ->where('reference_id', $reference)->lockForUpdate()->first();
            if (!$payment) return $this->ack();

            $order = $payment->order()->lockForUpdate()->first();
            if ($order?->status === 'activated' && $payment->status === 'paid') return $this->ack();
            if ($payment->status === 'refunded') return $this->ack();

            $data = [
                'provider_payload' => $payload,
                'provider_transaction_id' => (string) ($payload['trx_id'] ?? $payload['transaction_id'] ?? $payment->provider_transaction_id),
            ];

            if (!$guard->isPaid($payload)) {
                if ($payment->status === 'pending' && $failed = $guard->failedStatus($payload)) {
                    $data['status'] = $failed;
                    if ($order && $order->status !== 'activated') $order->update(['status' => $failed]);
                }
                if ($payment->status !== 'paid') $payment->update($data);
                return $this->ack();
            }

            $subscription = $order?->subscription_id
                ? Subscription::query()->whereKey($order->subscription_id)->lockForUpdate()->first()
                : null;
            $organizationsMatch = $order
                && $guard->organizationMatches((int) $payment->organization_id, (int) $order->organization_id)
                && $guard->organizationMatches((int) $payment->organization_id, $subscription ? (int) $subscription->organization_id : null)
                && $guard->storedAmountsMatch((float) $payment->amount, (float) $order->total_amount);
            $issue = $guard->paymentIssue($payload, $reference, (float) $payment->amount, $ipaymu->callbackAmount($payload));
            if (!$organizationsMatch || $issue !== null || !$subscription || $subscription->status !== 'active' ||
                ($subscription->ends_at && $subscription->ends_at->lte(now()))) {
                return $this->review($payment, $order, $data, $reference,
                    $issue ?? 'Add-on tenant mismatch or subscription is not active.');
            }

            if ($order->status === 'activated') {
                // An activated entitlement must never be granted twice.
                $payment->update($data + ['status' => 'paid']);
                return $this->ack();
            }

            $payment->update($data + [
                'status' => 'paid',
                'paid_at' => $payment->paid_at ?: now(),
                'failure_reason' => null,
            ]);

            try {
                // activate() locks payment, order and subscription and is idempotent.
                $addons->activate($payment->fresh());
            } catch (\Throwable $e) {
                report($e);
                return $this->review($payment, $order, $data, $reference, 'Add-on activation failed; manual review required.');
            }

            return $this->ack();
        }, 3);
    }

    private function review($payment, $order, array $data, string $reference, string $reason): JsonResponse
    {
        $payment->update($data + [
            'status' => 'pending_verification',
            'failure_reason' => $reason,
        ]);
        if ($order && $order->status !== 'activated') $order->update(['status' => 'pending_verification']);
        Log::warning('iPaymu add-on callback awaiting review', ['reference' => $reference, 'reason' => $reason]);
        return $this->ack();
    }

    private function ack(): JsonResponse
    {
        return response()->json(['message' => 'OK'], 200);
    }
}
