<?php
namespace App\Http\Controllers;
use App\Models\Payment;
use App\Services\IpaymuService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class IpaymuWebhookController extends Controller {
    public function __invoke(Request $request, IpaymuService $ipaymu, SubscriptionService $subscriptions): JsonResponse {
        $payload=$request->all(); $signature=$request->header('X-Signature');
        if (!$ipaymu->validateCallback($payload,$signature)) return response()->json(['message'=>'Invalid signature'],401);
        $reference=(string)($payload['reference_id'] ?? $payload['referenceId'] ?? $payload['reference'] ?? '');
        $payment=Payment::where('reference_id',$reference)->first();
        if (!$payment) return response()->json(['message'=>'OK'],200);
        $statusRaw=strtolower((string)($payload['status'] ?? $payload['status_code'] ?? $payload['trx_status'] ?? ''));
        $paid=in_array($statusRaw,['1','success','paid','berhasil','completed'],true) || (string)($payload['status'] ?? '') === '1';
        if ($paid && $payment->status !== 'paid') {
            $payment->update(['status'=>'paid','paid_at'=>now(),'provider_transaction_id'=>(string)($payload['trx_id'] ?? $payload['transaction_id'] ?? $payment->provider_transaction_id),'provider_payload'=>$payload]);
            if ($payment->subscription->status !== 'active') $subscriptions->activate($payment->subscription);
        } elseif (!$paid && $payment->status === 'pending') {
            $payment->update(['provider_payload'=>$payload]);
        }
        return response()->json(['message'=>'OK'],200);
    }
}
