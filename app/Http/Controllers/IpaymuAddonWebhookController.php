<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionAddonPayment;
use App\Services\IpaymuService;
use App\Services\SubscriptionAddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpaymuAddonWebhookController extends Controller
{
    public function __invoke(Request $request, IpaymuService $ipaymu, SubscriptionAddonService $addons): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Signature');
        if (!$ipaymu->validateCallback($payload,$signature)) {
            return response()->json(['message'=>'Invalid signature'],401);
        }

        $reference = (string)($payload['reference_id'] ?? $payload['referenceId'] ?? $payload['reference'] ?? '');
        $payment = SubscriptionAddonPayment::query()->where('reference_id',$reference)->with('order')->first();
        if (!$payment) return response()->json(['message'=>'OK'],200);

        $statusRaw = strtolower((string)($payload['status'] ?? $payload['trx_status'] ?? ''));
        $statusCode = (int)($payload['transaction_status_code'] ?? $payload['status_code'] ?? 0);
        $paid = in_array($statusRaw,['success','paid','berhasil','completed'],true) || in_array($statusCode,[1,6],true);
        $providerTransaction = (string)($payload['trx_id'] ?? $payload['transaction_id'] ?? $payment->provider_transaction_id);

        $payment->update([
            'provider_payload'=>$payload,
            'provider_transaction_id'=>$providerTransaction,
        ]);

        if (!$paid) {
            if (in_array($statusRaw,['failed','failure','expired','cancelled','canceled'],true) && !in_array($payment->status,['paid','refunded'],true)) {
                $mapped = in_array($statusRaw,['cancelled','canceled'],true) ? 'cancelled' : (str_contains($statusRaw,'expire') ? 'expired' : 'failed');
                $payment->update(['status'=>$mapped]);
                if ($payment->order && $payment->order->status !== 'activated') $payment->order->update(['status'=>$mapped]);
            }
            return response()->json(['message'=>'OK'],200);
        }

        if ($payment->order?->status === 'activated' && $payment->status === 'paid') {
            return response()->json(['message'=>'OK'],200);
        }

        $callbackAmount = $ipaymu->callbackAmount($payload);
        if ($callbackAmount !== null && abs($callbackAmount-(float)$payment->amount) > 1) {
            $payment->update([
                'status'=>'pending_verification',
                'failure_reason'=>'Gateway callback amount mismatch. Add-on activation requires manual review.',
            ]);
            $payment->order?->update(['status'=>'pending_verification']);
            return response()->json(['message'=>'OK'],200);
        }

        $payment->update([
            'status'=>'paid',
            'paid_at'=>$payment->paid_at ?: now(),
            'failure_reason'=>null,
        ]);

        try {
            $addons->activate($payment->fresh());
        } catch (\Throwable $e) {
            report($e);
            $payment->fresh()->update(['failure_reason'=>$e->getMessage()]);
            if ($payment->order?->status !== 'activated') $payment->order?->update(['status'=>'pending_verification']);
        }

        return response()->json(['message'=>'OK'],200);
    }
}
