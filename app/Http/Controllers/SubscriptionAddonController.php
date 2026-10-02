<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use App\Services\EntitlementService;
use App\Services\IpaymuService;
use App\Services\SubscriptionAddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionAddonController extends Controller
{
    public function index(Request $request, IpaymuService $ipaymu, EntitlementService $entitlements, SubscriptionAddonService $addons): Response
    {
        $organization = $request->user()->organization;
        $addons->assertPurchasable($organization);
        $subscription = $addons->activeSubscription($organization)->load(['plan','businessConfiguration']);

        try {
            $paymentChannels = $ipaymu->paymentChannels();
        } catch (\Throwable $e) {
            report($e);
            $paymentChannels = $this->fallbackPaymentChannels();
        }

        $catalog = SubscriptionAddon::query()->active()->orderBy('sort_order')->orderBy('id')->get()->map(fn($addon)=>[
            'id'=>$addon->id,
            'key'=>$addon->key,
            'name'=>$addon->name,
            'description'=>$addon->description,
            'resource_key'=>$addon->resource_key,
            'resource_quantity'=>(int)$addon->resource_quantity,
            'monthly_price'=>(int)$addon->monthly_price,
            'annual_price'=>(int)$addon->annual_price,
            'current_price'=>$addon->priceFor($subscription->billing_cycle),
        ])->values();

        $orders = SubscriptionAddonOrder::query()
            ->where('organization_id',$organization->id)
            ->with(['addon:id,key,name','payment:id,subscription_addon_order_id,reference_id,status,amount,paid_at'])
            ->latest('id')->limit(30)->get()->map(fn($order)=>[
                'id'=>$order->id,
                'addon'=>$order->addon?->only(['key','name']),
                'billing_cycle'=>$order->billing_cycle,
                'units'=>(int)$order->units,
                'resource_key'=>$order->resource_key,
                'resource_quantity'=>(int)$order->resource_quantity,
                'total_amount'=>(int)$order->total_amount,
                'status'=>$order->status,
                'starts_at'=>$order->starts_at?->toIso8601String(),
                'ends_at'=>$order->ends_at?->toIso8601String(),
                'activated_at'=>$order->activated_at?->toIso8601String(),
                'created_at'=>$order->created_at?->toIso8601String(),
                'payment'=>$order->payment ? [
                    'reference_id'=>$order->payment->reference_id,
                    'status'=>$order->payment->status,
                    'amount'=>(int)$order->payment->amount,
                    'paid_at'=>$order->payment->paid_at?->toIso8601String(),
                ] : null,
            ])->values();

        return Inertia::render('Subscription/Addons',[
            'subscription'=>[
                'id'=>$subscription->id,
                'billing_cycle'=>$subscription->billing_cycle,
                'ends_at'=>$subscription->ends_at?->toIso8601String(),
                'plan'=>$subscription->plan?->only(['id','key','name']),
                'configuration'=>$subscription->businessConfiguration?->only(['id','key','name']),
            ],
            'entitlements'=>$entitlements->snapshot($organization),
            'addons'=>$catalog,
            'orders'=>$orders,
            'paymentChannels'=>$paymentChannels,
            'gateway'=>[
                'provider'=>'iPaymu','mode'=>$ipaymu->mode(),'configured'=>$ipaymu->configured(),'realtime'=>'sse',
            ],
            'maxUnits'=>(int)config('subscription_addons.max_units_per_order',20),
        ]);
    }

    public function pay(Request $request, IpaymuService $ipaymu, SubscriptionAddonService $addons): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'addon_key'=>['required','string',Rule::exists('subscription_addons','key')->where(fn($q)=>$q->where('is_active',true))],
            'units'=>['required','integer','min:1','max:'.max(1,(int)config('subscription_addons.max_units_per_order',20))],
            'payment_method'=>['required','string','max:30'],
            'payment_channel'=>['required','string','max:30'],
        ]);

        $user = $request->user();
        $addon = SubscriptionAddon::query()->active()->where('key',$data['addon_key'])->firstOrFail();
        $order = $addons->createOrder($user,$addon,(int)$data['units']);

        $payment = SubscriptionAddonPayment::create([
            'organization_id'=>$user->organization_id,
            'subscription_addon_order_id'=>$order->id,
            'provider'=>'ipaymu',
            'reference_id'=>'SVA-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
            'status'=>'pending',
            'amount'=>$order->total_amount,
            'payment_method'=>$data['payment_method'],
            'payment_channel'=>$data['payment_channel'],
        ]);

        try {
            $result = $ipaymu->createAddonCheckout($payment,[
                'name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,
                'payment_method'=>$data['payment_method'],'payment_channel'=>$data['payment_channel'],
            ]);

            $presentation = $ipaymu->directPaymentPresentation($result);
            $checkout = $presentation['checkout_url'];
            $payment->update([
                'provider_transaction_id'=>(string)($presentation['transaction_id'] ?: $presentation['session_id'] ?: ''),
                'checkout_url'=>$checkout,
                'provider_payload'=>$result,
                'failure_reason'=>null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'reference'=>$payment->reference_id,
                    'checkout_url'=>$checkout,
                    'presentation'=>$presentation,
                    'stream_url'=>route('subscription.addons.payment-stream',['reference'=>$payment->reference_id]),
                    'status_url'=>route('subscription.addons.status',['reference'=>$payment->reference_id]),
                    'result_url'=>route('subscription.addons.payment-result',['reference'=>$payment->reference_id]),
                    'environment'=>$ipaymu->mode(),
                    'order'=>[
                        'id'=>$order->id,'addon'=>$addon->name,'units'=>$order->units,
                        'resource_key'=>$order->resource_key,'resource_quantity'=>$order->resource_quantity,
                        'total_amount'=>$order->total_amount,
                    ],
                ]);
            }

            return $checkout
                ? redirect()->away($checkout)
                : redirect()->route('subscription.addons.payment-result',['reference'=>$payment->reference_id]);
        } catch (\Throwable $e) {
            report($e);
            $payment->update(['status'=>'failed','failure_reason'=>$e->getMessage()]);
            $order->update(['status'=>'failed']);
            return $request->expectsJson() ? response()->json(['message'=>$e->getMessage()],422) : back()->with('error',$e->getMessage());
        }
    }

    public function result(Request $request, IpaymuService $ipaymu): Response
    {
        $reference = trim((string)$request->query('reference'));
        $payment = SubscriptionAddonPayment::query()
            ->where('organization_id',$request->user()->organization_id)
            ->where('reference_id',$reference)
            ->with(['order.addon:id,key,name','order.subscription.plan:id,key,name'])
            ->first();

        return Inertia::render('Subscription/AddonPaymentResult',[
            'payment'=>$payment ? [
                'reference_id'=>$payment->reference_id,'status'=>$payment->status,'amount'=>(int)$payment->amount,
                'paid_at'=>$payment->paid_at?->toIso8601String(),'payment_method'=>$payment->payment_method,
                'payment_channel'=>$payment->payment_channel,'failure_reason'=>$payment->failure_reason,
            ] : null,
            'presentation'=>$payment ? array_merge(
                $ipaymu->directPaymentPresentation($payment->provider_payload ?? []),
                ['qr_proxy_url'=>route('subscription.addons.payment-qr',['reference'=>$payment->reference_id])]
            ) : null,
            'order'=>$payment?->order ? [
                'id'=>$payment->order->id,'status'=>$payment->order->status,
                'addon'=>$payment->order->addon?->only(['key','name']),
                'resource_key'=>$payment->order->resource_key,'resource_quantity'=>(int)$payment->order->resource_quantity,
                'ends_at'=>$payment->order->ends_at?->toIso8601String(),
                'plan'=>$payment->order->subscription?->plan?->only(['key','name']),
            ] : null,
            'gateway'=>['provider'=>'iPaymu','mode'=>$ipaymu->mode(),'realtime'=>'sse'],
        ]);
    }

    public function status(Request $request): array
    {
        $reference = trim((string)$request->query('reference'));
        $payment = SubscriptionAddonPayment::query()
            ->where('organization_id',$request->user()->organization_id)
            ->where('reference_id',$reference)->with('order:id,status,activated_at')->first();
        return $this->statusPayload($payment,$reference);
    }

    public function stream(Request $request): StreamedResponse
    {
        $reference = trim((string)$request->query('reference'));
        abort_if($reference === '',422,'Reference pembayaran wajib diisi.');
        $organizationId = (int)$request->user()->organization_id;
        abort_unless(SubscriptionAddonPayment::query()->where('organization_id',$organizationId)->where('reference_id',$reference)->exists(),404);

        return response()->stream(function() use($organizationId,$reference) {
            @set_time_limit(0); @ini_set('zlib.output_compression','0'); @ini_set('output_buffering','off');
            echo "retry: 2000\n\n";
            $lastFingerprint=null; $startedAt=microtime(true); $lastHeartbeat=0.0;
            while ((microtime(true)-$startedAt)<25) {
                if (connection_aborted()) break;
                $payment=SubscriptionAddonPayment::query()->where('organization_id',$organizationId)->where('reference_id',$reference)->with('order:id,status,activated_at')->first();
                $payload=$this->statusPayload($payment,$reference); $fingerprint=sha1(json_encode($payload)?:'');
                if ($fingerprint!==$lastFingerprint) { echo "event: payment\n"; echo 'data: '.json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n"; $lastFingerprint=$fingerprint; }
                if (($payload['status']??null)==='paid' && ($payload['active']??false)) { echo "event: complete\n"; echo 'data: '.json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n"; $this->flushStream(); break; }
                if (in_array(($payload['status']??null),['failed','expired','cancelled','canceled','pending_verification'],true)) { echo "event: terminal\n"; echo 'data: '.json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n"; $this->flushStream(); break; }
                if ((microtime(true)-$lastHeartbeat)>=8) { echo "event: heartbeat\n"; echo 'data: '.json_encode(['at'=>now()->toIso8601String()])."\n\n"; $lastHeartbeat=microtime(true); }
                $this->flushStream(); usleep(800000);
            }
        },200,['Content-Type'=>'text/event-stream','Cache-Control'=>'no-cache, no-transform','Connection'=>'keep-alive','X-Accel-Buffering'=>'no']);
    }

    private function statusPayload(?SubscriptionAddonPayment $payment,string $reference): array
    {
        return [
            'reference'=>$reference,'status'=>$payment?->status??'unknown',
            'active'=>$payment?->order?->status==='activated',
            'paid_at'=>$payment?->paid_at?->toIso8601String(),
            'order_status'=>$payment?->order?->status,
            'activated_at'=>$payment?->order?->activated_at?->toIso8601String(),
            'updated_at'=>$payment?->updated_at?->toIso8601String(),
        ];
    }

    private function flushStream(): void { if (ob_get_level()>0) @ob_flush(); flush(); }

    private function fallbackPaymentChannels(): array
    {
        return [
            ['code'=>'va','name'=>'Virtual Account','description'=>'Transfer bank melalui Virtual Account.','channels'=>[
                ['code'=>'bca','name'=>'BCA','description'=>'BCA Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                ['code'=>'mandiri','name'=>'Mandiri','description'=>'Mandiri Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                ['code'=>'bni','name'=>'BNI','description'=>'BNI Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                ['code'=>'bri','name'=>'BRI','description'=>'BRI Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
            ]],
            ['code'=>'qris','name'=>'QRIS','description'=>'Pembayaran QRIS.','channels'=>[
                ['code'=>'mpm','name'=>'QRIS','description'=>'QRIS','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
            ]],
        ];
    }
}
