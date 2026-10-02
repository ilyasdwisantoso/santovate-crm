<?php

namespace App\Http\Controllers;

use App\Models\BusinessConfiguration;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\IpaymuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionController extends Controller
{
    public function checkout(Request $request, IpaymuService $ipaymu): Response
    {
        $org = $request->user()->organization;
        $pending = $org->subscriptions()
            ->with(['plan','businessConfiguration'])
            ->where('status','pending')
            ->latest()
            ->first();

        try {
            $paymentChannels = $ipaymu->paymentChannels();
        } catch (\Throwable $e) {
            report($e);
            $paymentChannels = [
                [
                    'code'=>'va',
                    'name'=>'Virtual Account',
                    'description'=>'Transfer bank melalui nomor Virtual Account.',
                    'channels'=>[
                        ['code'=>'bca','name'=>'BCA','description'=>'BCA Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                        ['code'=>'mandiri','name'=>'Mandiri','description'=>'Mandiri Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                        ['code'=>'bni','name'=>'BNI','description'=>'BNI Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                        ['code'=>'bri','name'=>'BRI','description'=>'BRI Virtual Account','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                    ],
                ],
                [
                    'code'=>'qris',
                    'name'=>'QRIS',
                    'description'=>'Pembayaran QRIS.',
                    'channels'=>[
                        ['code'=>'mpm','name'=>'QRIS','description'=>'QRIS','logo'=>null,'feature_status'=>'active','health_status'=>'online','fee'=>null,'fee_type'=>null],
                    ],
                ],
            ];
        }

        return Inertia::render('Public/Checkout', [
            'subscription'=>$pending,
            'plans'=>SubscriptionPlan::where('is_active',true)->orderBy('sort_order')->get(),
            'configurations'=>BusinessConfiguration::where('is_active',true)->orderBy('sort_order')->get(),
            'paymentChannels'=>$paymentChannels,
            'gateway'=>[
                'provider'=>'iPaymu',
                'mode'=>$ipaymu->mode(),
                'configured'=>$ipaymu->configured(),
                'realtime'=>'sse',
            ],
            'support'=>config('santovate.support'),
        ]);
    }

    public function updateSelection(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'plan_key'=>['required','exists:subscription_plans,key'],
            'configuration_key'=>['required','exists:business_configurations,key'],
            'billing_cycle'=>['required','in:monthly,annual'],
        ]);

        $org = $request->user()->organization;
        $plan = SubscriptionPlan::where('key',$data['plan_key'])->where('is_active',true)->firstOrFail();
        $cfg = BusinessConfiguration::where('key',$data['configuration_key'])->where('is_active',true)->firstOrFail();
        $base = $data['billing_cycle'] === 'annual' ? $plan->annual_price : $plan->monthly_price;
        $addon = $data['billing_cycle'] === 'annual' ? $cfg->annual_addon_price : $cfg->monthly_addon_price;

        $sub = $org->subscriptions()->where('status','pending')->latest()->first();
        if (!$sub) {
            $sub = new Subscription(['organization_id'=>$org->id,'status'=>'pending']);
        }

        $sub->fill([
            'subscription_plan_id'=>$plan->id,
            'business_configuration_id'=>$cfg->id,
            'billing_cycle'=>$data['billing_cycle'],
            'base_amount'=>$base,
            'configuration_amount'=>$addon,
            'total_amount'=>$base+$addon,
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'saved'=>true,
                'selection'=>[
                    'plan_key'=>$plan->key,
                    'configuration_key'=>$cfg->key,
                    'billing_cycle'=>$sub->billing_cycle,
                ],
                'subscription'=>[
                    'id'=>$sub->id,
                    'base_amount'=>(int)$sub->base_amount,
                    'configuration_amount'=>(int)$sub->configuration_amount,
                    'total_amount'=>(int)$sub->total_amount,
                    'billing_cycle'=>$sub->billing_cycle,
                    'plan'=>$plan->only(['id','key','name']),
                    'business_configuration'=>$cfg->only(['id','key','name']),
                ],
            ]);
        }

        return back()->with('success','Paket dan konfigurasi diperbarui.');
    }

    public function pay(Request $request, IpaymuService $ipaymu): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'payment_method'=>['required','string','max:30'],
            'payment_channel'=>['required','string','max:30'],
        ]);

        $user = $request->user();
        $sub = $user->organization->subscriptions()->where('status','pending')->latest()->firstOrFail();
        $payment = Payment::create([
            'organization_id'=>$user->organization_id,
            'subscription_id'=>$sub->id,
            'provider'=>'ipaymu',
            'reference_id'=>'SVT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
            'status'=>'pending',
            'amount'=>$sub->total_amount,
            'payment_method'=>$data['payment_method'],
            'payment_channel'=>$data['payment_channel'],
        ]);

        try {
            $result = $ipaymu->createCheckout($payment, [
                'name'=>$user->name,
                'email'=>$user->email,
                'phone'=>$user->phone,
                'payment_method'=>$data['payment_method'],
                'payment_channel'=>$data['payment_channel'],
            ]);

            $presentation = $ipaymu->directPaymentPresentation($result);
            $checkout = $presentation['checkout_url'];
            $payment->update([
                'provider_transaction_id'=>(string)($presentation['transaction_id'] ?: $presentation['session_id'] ?: ''),
                'checkout_url'=>$checkout,
                'provider_payload'=>$result,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'reference'=>$payment->reference_id,
                    'checkout_url'=>$checkout,
                    'presentation'=>$presentation,
                    'stream_url'=>route('subscription.payment-stream',['reference'=>$payment->reference_id]),
                    'status_url'=>route('subscription.status',['reference'=>$payment->reference_id]),
                    'result_url'=>route('subscription.payment-result',['reference'=>$payment->reference_id]),
                    'environment'=>$ipaymu->mode(),
                ]);
            }

            return $checkout
                ? redirect()->away($checkout)
                : redirect()->route('subscription.payment-result',['reference'=>$payment->reference_id]);
        } catch (\Throwable $e) {
            report($e);
            $payment->update(['status'=>'failed']);
            if ($request->expectsJson()) {
                return response()->json(['message'=>$e->getMessage()], 422);
            }
            return back()->with('error',$e->getMessage());
        }
    }

    public function result(Request $request, IpaymuService $ipaymu): Response
    {
        $reference = (string)$request->query('reference');
        $payment = Payment::query()
            ->where('organization_id',$request->user()->organization_id)
            ->where('reference_id',$reference)
            ->with('subscription.plan','subscription.businessConfiguration')
            ->first();

        return Inertia::render('Public/PaymentResult', [
            'payment'=>$payment ? $payment->only(['reference_id','status','amount','paid_at','payment_method','payment_channel']) : null,
            'presentation'=>$payment ? array_merge(
                $ipaymu->directPaymentPresentation($payment->provider_payload ?? []),
                ['qr_proxy_url'=>route('subscription.payment-qr',['reference'=>$payment->reference_id])]
            ) : null,
            'subscription'=>$payment?->subscription,
            'gateway'=>[
                'provider'=>'iPaymu',
                'mode'=>$ipaymu->mode(),
                'realtime'=>'sse',
            ],
        ]);
    }

    public function status(Request $request): array
    {
        $reference = (string)$request->query('reference');
        $payment = Payment::query()
            ->where('organization_id',$request->user()->organization_id)
            ->where('reference_id',$reference)
            ->with('subscription:id,status,activated_at')
            ->first();

        return $this->statusPayload($payment, $reference);
    }

    public function stream(Request $request): StreamedResponse
    {
        $reference = trim((string)$request->query('reference'));
        abort_if($reference === '', 422, 'Reference pembayaran wajib diisi.');

        $organizationId = (int)$request->user()->organization_id;
        abort_unless(
            Payment::query()->where('organization_id',$organizationId)->where('reference_id',$reference)->exists(),
            404
        );

        return response()->stream(function () use ($organizationId, $reference) {
            @set_time_limit(0);
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');

            echo "retry: 2000\n\n";
            $lastFingerprint = null;
            $startedAt = microtime(true);
            $lastHeartbeat = 0.0;

            while ((microtime(true) - $startedAt) < 25) {
                if (connection_aborted()) break;

                $payment = Payment::query()
                    ->where('organization_id',$organizationId)
                    ->where('reference_id',$reference)
                    ->with('subscription:id,status,activated_at')
                    ->first();

                $payload = $this->statusPayload($payment, $reference);
                $fingerprint = sha1(json_encode($payload) ?: '');

                if ($fingerprint !== $lastFingerprint) {
                    echo "event: payment\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n";
                    $lastFingerprint = $fingerprint;
                }

                if (($payload['status'] ?? null) === 'paid' && ($payload['active'] ?? false)) {
                    echo "event: complete\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n";
                    $this->flushStream();
                    break;
                }

                if (in_array(($payload['status'] ?? null), ['failed','expired','cancelled','canceled'], true)) {
                    echo "event: terminal\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n";
                    $this->flushStream();
                    break;
                }

                if ((microtime(true) - $lastHeartbeat) >= 8) {
                    echo "event: heartbeat\n";
                    echo 'data: '.json_encode(['at'=>now()->toIso8601String()])."\n\n";
                    $lastHeartbeat = microtime(true);
                }

                $this->flushStream();
                usleep(800000);
            }
        }, 200, [
            'Content-Type'=>'text/event-stream',
            'Cache-Control'=>'no-cache, no-transform',
            'Connection'=>'keep-alive',
            'X-Accel-Buffering'=>'no',
        ]);
    }

    private function statusPayload(?Payment $payment, string $reference): array
    {
        return [
            'reference'=>$reference,
            'status'=>$payment?->status ?? 'unknown',
            'active'=>$payment?->subscription?->status === 'active',
            'paid_at'=>$payment?->paid_at?->toIso8601String(),
            'subscription_status'=>$payment?->subscription?->status,
            'updated_at'=>$payment?->updated_at?->toIso8601String(),
        ];
    }

    private function flushStream(): void
    {
        if (ob_get_level() > 0) @ob_flush();
        flush();
    }
}
