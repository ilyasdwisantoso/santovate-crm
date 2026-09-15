<?php
namespace App\Http\Controllers;
use App\Models\BusinessConfiguration;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\IpaymuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
class SubscriptionController extends Controller {
    public function checkout(Request $request): Response {
        $org=$request->user()->organization;
        $pending=$org->subscriptions()->with(['plan','businessConfiguration'])->where('status','pending')->latest()->first();
        return Inertia::render('Public/Checkout',['subscription'=>$pending,'plans'=>SubscriptionPlan::where('is_active',true)->orderBy('sort_order')->get(),'configurations'=>BusinessConfiguration::where('is_active',true)->orderBy('sort_order')->get(),'support'=>config('santovate.support')]);
    }
    public function updateSelection(Request $request): RedirectResponse {
        $data=$request->validate(['plan_key'=>['required','exists:subscription_plans,key'],'configuration_key'=>['required','exists:business_configurations,key'],'billing_cycle'=>['required','in:monthly,annual']]);
        $org=$request->user()->organization; $plan=SubscriptionPlan::where('key',$data['plan_key'])->firstOrFail(); $cfg=BusinessConfiguration::where('key',$data['configuration_key'])->firstOrFail();
        $base=$data['billing_cycle']==='annual'?$plan->annual_price:$plan->monthly_price; $addon=$data['billing_cycle']==='annual'?$cfg->annual_addon_price:$cfg->monthly_addon_price;
        $sub=$org->subscriptions()->where('status','pending')->latest()->first();
        if (!$sub) $sub=new Subscription(['organization_id'=>$org->id,'status'=>'pending']);
        $sub->fill(['subscription_plan_id'=>$plan->id,'business_configuration_id'=>$cfg->id,'billing_cycle'=>$data['billing_cycle'],'base_amount'=>$base,'configuration_amount'=>$addon,'total_amount'=>$base+$addon])->save();
        return back()->with('success','Paket dan konfigurasi diperbarui.');
    }
    public function pay(Request $request, IpaymuService $ipaymu): RedirectResponse {
        $data=$request->validate(['payment_method'=>['required','string','max:30'],'payment_channel'=>['required','string','max:30']]);
        $user=$request->user(); $sub=$user->organization->subscriptions()->where('status','pending')->latest()->firstOrFail();
        $payment=Payment::create(['organization_id'=>$user->organization_id,'subscription_id'=>$sub->id,'provider'=>'ipaymu','reference_id'=>'SVT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),'status'=>'pending','amount'=>$sub->total_amount,'payment_method'=>$data['payment_method'],'payment_channel'=>$data['payment_channel']]);
        try {
            $result=$ipaymu->createCheckout($payment,['name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,'payment_method'=>$data['payment_method'],'payment_channel'=>$data['payment_channel']]);
            $checkout=data_get($result,'Data.Url') ?? data_get($result,'Data.url') ?? data_get($result,'Url');
            $payment->update(['provider_transaction_id'=>(string)(data_get($result,'Data.SessionId') ?? data_get($result,'Data.TransactionId') ?? ''),'checkout_url'=>$checkout,'provider_payload'=>$result]);
            if (!$checkout) return redirect()->route('subscription.payment-result',['reference'=>$payment->reference_id])->with('error','iPaymu tidak mengembalikan URL pembayaran.');
            return redirect()->away($checkout);
        } catch (\Throwable $e) { report($e); $payment->update(['status'=>'failed']); return back()->with('error',$e->getMessage()); }
    }
    public function result(Request $request): Response {
        $reference=(string)$request->query('reference');
        $payment=Payment::query()->where('organization_id',$request->user()->organization_id)->where('reference_id',$reference)->with('subscription.plan','subscription.businessConfiguration')->first();
        return Inertia::render('Public/PaymentResult',['payment'=>$payment ? $payment->only(['reference_id','status','amount','paid_at']) : null,'subscription'=>$payment?->subscription]);
    }
    public function status(Request $request): array {
        $reference=(string)$request->query('reference');
        $p=Payment::where('organization_id',$request->user()->organization_id)->where('reference_id',$reference)->first();
        return ['status'=>$p?->status ?? 'unknown','active'=>(bool)$request->user()->organization?->activeSubscription()];
    }
}
