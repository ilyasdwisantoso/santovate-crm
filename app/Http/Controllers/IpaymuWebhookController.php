<?php
namespace App\Http\Controllers;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\FinanceService;
use App\Services\IpaymuService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class IpaymuWebhookController extends Controller {
    public function __invoke(Request $request,IpaymuService $ipaymu,SubscriptionService $subscriptions,FinanceService $finance):JsonResponse {
        $payload=$request->all();$signature=$request->header('X-Signature');
        if(!$ipaymu->validateCallback($payload,$signature))return response()->json(['message'=>'Invalid signature'],401);
        $reference=(string)($payload['reference_id']??$payload['referenceId']??$payload['reference']??'');
        $statusRaw=strtolower((string)($payload['status']??$payload['trx_status']??''));
        $statusCode=(int)($payload['transaction_status_code']??$payload['status_code']??0);
        $paid=in_array($statusRaw,['success','paid','berhasil','completed'],true)||in_array($statusCode,[1,6],true);

        $commercial=PaymentTransaction::where('reference_id',$reference)->with('invoice')->first();
        if($commercial){
            $commercial->update(['provider_payload'=>$payload,'provider_transaction_id'=>(string)($payload['trx_id']??$payload['transaction_id']??$commercial->provider_transaction_id)]);
            if(!$paid){
                if(in_array($statusRaw,['failed','failure','expired','cancelled','canceled'],true)&&!in_array($commercial->status,['paid','refunded'],true)){
                    $mapped=in_array($statusRaw,['cancelled','canceled'],true)?'cancelled':(str_contains($statusRaw,'expire')?'expired':'failed');
                    $commercial->update(['status'=>$mapped]);
                }
                return response()->json(['message'=>'OK'],200);
            }
            if(in_array($commercial->status,['paid','refunded'],true))return response()->json(['message'=>'OK'],200);
            if($commercial->invoice?->status==='void'){
                $commercial->update(['status'=>'pending_verification','failure_reason'=>'Payment callback received for a void invoice. Finance review required.']);
                return response()->json(['message'=>'OK'],200);
            }

            $callbackAmount=$ipaymu->callbackAmount($payload);
            if($callbackAmount!==null&&abs($callbackAmount-(float)$commercial->amount)>1){
                $commercial->update(['status'=>'pending_verification','failure_reason'=>'Gateway callback amount mismatch. Finance review required.']);
                return response()->json(['message'=>'OK'],200);
            }

            $invoice=$finance->syncInvoice($commercial->invoice);
            if((float)$commercial->amount>$invoice->outstanding_amount+0.01){
                $commercial->update(['status'=>'pending_verification','failure_reason'=>'Gateway payment exceeds current invoice outstanding. Finance review required.']);
                return response()->json(['message'=>'OK'],200);
            }

            $commercial->update(['status'=>'paid','paid_at'=>now(),'verified_at'=>now(),'failure_reason'=>null]);
            $finance->syncInvoice($invoice,[
                'commercial_invoice_id'=>$invoice->id,
                'payment_transaction_id'=>$commercial->id,
                'reference_number'=>$commercial->reference_id,
                'notes'=>'Verified automatically from iPaymu webhook.',
            ]);
            return response()->json(['message'=>'OK'],200);
        }

        $payment=Payment::where('reference_id',$reference)->first();
        if(!$payment)return response()->json(['message'=>'OK'],200);
        if($paid&&$payment->status!=='paid'){
            $payment->update(['status'=>'paid','paid_at'=>now(),'provider_transaction_id'=>(string)($payload['trx_id']??$payload['transaction_id']??$payment->provider_transaction_id),'provider_payload'=>$payload]);
            if($payment->subscription->status!=='active')$subscriptions->activate($payment->subscription);
        }elseif(!$paid&&$payment->status==='pending'){
            $payment->update(['provider_payload'=>$payload]);
        }
        return response()->json(['message'=>'OK'],200);
    }
}
