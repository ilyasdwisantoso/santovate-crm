<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionAddonPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IpaymuService
{
    public function mode(): string
    {
        return config('santovate.ipaymu.mode') === 'production' ? 'production' : 'sandbox';
    }

    public function credentials(?string $mode = null): array
    {
        $mode = $mode ?: $this->mode();
        $config = (array) config("santovate.ipaymu.{$mode}", []);

        return [
            'va' => (string) ($config['va'] ?? ''),
            'api_key' => (string) ($config['api_key'] ?? ''),
            'url' => rtrim((string) ($config['url'] ?? ''), '/'),
        ];
    }

    public function baseUrl(): string
    {
        return $this->credentials()['url'];
    }

    public function configured(): bool
    {
        $credentials = $this->credentials();
        return filled($credentials['va']) && filled($credentials['api_key']) && filled($credentials['url']);
    }

    public function createCheckout(Payment $payment, array $buyer): array
    {
        return $this->createCheckoutRequest(
            (string) $payment->reference_id,
            (float) $payment->amount,
            $buyer,
            route('subscription.payment-result', ['reference'=>$payment->reference_id]),
            route('subscription.checkout'),
            route('api.ipaymu.callback')
        );
    }

    public function createAddonCheckout(SubscriptionAddonPayment $payment, array $buyer): array
    {
        return $this->createCheckoutRequest(
            (string) $payment->reference_id,
            (float) $payment->amount,
            $buyer,
            route('subscription.addons.payment-result', ['reference'=>$payment->reference_id]),
            route('subscription.addons.index'),
            route('api.ipaymu.addon-callback')
        );
    }

    public function createCommercialCheckout(PaymentTransaction $payment, array $buyer): array
    {
        return $this->createCheckoutRequest(
            (string) $payment->reference_id,
            (float) $payment->amount,
            $buyer,
            route('commercial-payment.result', ['reference'=>$payment->reference_id]),
            route('commercial-payment.result', ['reference'=>$payment->reference_id])
        );
    }

    public function paymentChannels(): array
    {
        if (!$this->configured()) {
            return [];
        }

        return Cache::remember('ipaymu:channels:'.$this->mode(), now()->addMinutes(5), function (): array {
            $json = '{}';
            $response = Http::acceptJson()
                ->withHeaders($this->headers('GET', $json))
                ->get($this->baseUrl().'/api/v2/payment-channels');

            if (!$response->successful()) {
                throw new RuntimeException('Gagal mengambil channel iPaymu: '.$response->status());
            }

            $data = $response->json();
            if ((int) ($data['Status'] ?? 0) !== 200) {
                throw new RuntimeException((string) ($data['Message'] ?? 'Daftar channel iPaymu tidak tersedia.'));
            }

            $allowedMethods = array_values(array_filter((array) config('santovate.ipaymu.allowed_methods', [])));

            return collect($data['Data'] ?? [])->map(function ($method) {
                $channels = collect($method['Channels'] ?? [])->map(fn ($channel) => [
                    'code'=>(string) ($channel['Code'] ?? ''),
                    'name'=>(string) ($channel['Name'] ?? $channel['Code'] ?? ''),
                    'description'=>(string) ($channel['Description'] ?? ''),
                    'logo'=>$channel['Logo'] ?? null,
                    'feature_status'=>strtolower((string) ($channel['FeatureStatus'] ?? 'active')),
                    'health_status'=>strtolower((string) ($channel['HealthStatus'] ?? 'online')),
                    'fee'=>data_get($channel, 'TransactionFee.ActualFee'),
                    'fee_type'=>data_get($channel, 'TransactionFee.ActualFeeType'),
                ])->filter(fn ($channel) => filled($channel['code']))->values()->all();

                return [
                    'code'=>(string) ($method['Code'] ?? ''),
                    'name'=>(string) ($method['Name'] ?? $method['Code'] ?? ''),
                    'description'=>(string) ($method['Description'] ?? ''),
                    'channels'=>$channels,
                ];
            })->filter(fn ($method) => filled($method['code'])
                && count($method['channels']) > 0
                && (empty($allowedMethods) || in_array($method['code'], $allowedMethods, true)))
                ->values()->all();
        });
    }

    private function createCheckoutRequest(
        string $reference,
        float $amount,
        array $buyer,
        string $successUrl,
        string $cancelUrl,
        ?string $notifyUrl = null
    ): array {
        if (!$this->configured()) {
            throw new RuntimeException('Kredensial iPaymu belum dikonfigurasi.');
        }

        $payload = [
            'name'=>$buyer['name'],
            'phone'=>$buyer['phone'] ?? '',
            'email'=>$buyer['email'],
            'amount'=>(int) round($amount),
            'paymentMethod'=>$buyer['payment_method'] ?? 'va',
            'paymentChannel'=>$buyer['payment_channel'] ?? 'bca',
            'notifyUrl'=>$notifyUrl ?: route('api.ipaymu.callback'),
            'successUrl'=>$successUrl,
            'cancelUrl'=>$cancelUrl,
            'referenceId'=>$reference,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Payload checkout iPaymu tidak dapat di-encode.');
        }

        $response = Http::acceptJson()
            ->withHeaders($this->headers('POST', $json))
            ->withBody($json, 'application/json')
            ->post($this->baseUrl().'/api/v2/payment/direct');

        if (!$response->successful()) {
            throw new RuntimeException('iPaymu menolak request checkout: '.$response->status().' '.$response->body());
        }

        $data = $response->json();
        if ((int) ($data['Status'] ?? 0) !== 200) {
            throw new RuntimeException((string) ($data['Message'] ?? 'Checkout iPaymu gagal.'));
        }

        return $data;
    }

    public function validateCallback(array $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $normalized = $this->normalizeCallback($payload);
        unset($normalized['signature']);
        ksort($normalized, SORT_STRING);

        // iPaymu callback signing expects standard JSON slash escaping (\/),
        // so do not use JSON_UNESCAPED_SLASHES here.
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        // During a controlled sandbox -> production cutover, a delayed callback
        // may still arrive from the previous environment. Validate against both
        // configured merchant VAs without ever exposing them to the client.
        foreach (['sandbox', 'production'] as $mode) {
            $va = $this->credentials($mode)['va'];
            if (!filled($va)) {
                continue;
            }
            $expected = hash_hmac('sha256', $json, $va);
            if (hash_equals(strtolower($expected), strtolower(trim($signature)))) {
                return true;
            }
        }

        return false;
    }


    public function directPaymentPresentation(?array $payload): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = (array) data_get($payload, 'Data', []);

        $checkoutUrl = trim((string) ($data['Url'] ?? $data['url'] ?? data_get($payload, 'Url') ?? ''));
        $qrImage = trim((string) ($data['QrImage'] ?? ''));
        $qrTemplate = trim((string) ($data['QrTemplate'] ?? ''));
        $qrString = trim((string) ($data['QrString'] ?? ''));
        $paymentNo = trim((string) ($data['PaymentNo'] ?? ''));

        if ($checkoutUrl !== '') {
            $type = 'redirect';
        } elseif ($qrImage !== '' || $qrTemplate !== '' || $qrString !== '') {
            $type = 'qris';
        } elseif ($paymentNo !== '') {
            $type = 'payment_code';
        } else {
            $type = 'unknown';
        }

        return [
            'type'=>$type,
            'checkout_url'=>$checkoutUrl !== '' ? $checkoutUrl : null,
            'qr_image'=>$qrImage !== '' ? $qrImage : null,
            'qr_template'=>$qrTemplate !== '' ? $qrTemplate : null,
            'qr_string'=>$qrString !== '' ? $qrString : null,
            'payment_no'=>$paymentNo !== '' ? $paymentNo : null,
            'payment_name'=>filled($data['PaymentName'] ?? null) ? (string)$data['PaymentName'] : null,
            'via'=>filled($data['Via'] ?? null) ? (string)$data['Via'] : null,
            'channel'=>filled($data['Channel'] ?? null) ? (string)$data['Channel'] : null,
            'expired'=>filled($data['Expired'] ?? null) ? (string)$data['Expired'] : null,
            'transaction_id'=>filled($data['TransactionId'] ?? null) ? (string)$data['TransactionId'] : null,
            'session_id'=>filled($data['SessionId'] ?? null) ? (string)$data['SessionId'] : null,
        ];
    }
    public function callbackAmount(array $payload): ?float
    {
        foreach (['amount','total','sub_total','nominal','total_amount','trx_amount'] as $key) {
            if (array_key_exists($key, $payload) && is_numeric($payload[$key])) {
                return (float) $payload[$key];
            }
        }
        return null;
    }

    private function headers(string $method, string $json): array
    {
        $credentials = $this->credentials();
        $va = $credentials['va'];
        $apiKey = $credentials['api_key'];
        $bodyHash = strtolower(hash('sha256', $json));
        $stringToSign = strtoupper($method).':'.$va.':'.$bodyHash.':'.$apiKey;

        return [
            'va'=>$va,
            'signature'=>hash_hmac('sha256', $stringToSign, $apiKey),
            'timestamp'=>now()->format('YmdHis'),
        ];
    }

    private function normalizeCallback(array $payload): array
    {
        $integerFields = ['trx_id','status_code','transaction_status_code','paid_off'];
        $normalized = [];

        foreach ($payload as $key => $value) {
            if ($key === 'signature') {
                continue;
            }

            if ($key === 'is_escrow') {
                $normalized[$key] = in_array($value, [true, 1, '1', 'true'], true);
                continue;
            }

            if (in_array($key, $integerFields, true)) {
                $normalized[$key] = (int) $value;
                continue;
            }

            if ($key === 'additional_info') {
                if (is_array($value)) {
                    $normalized[$key] = $value;
                } elseif ($value === null || $value === '' || $value === '[]') {
                    $normalized[$key] = [];
                } else {
                    $normalized[$key] = (string) $value;
                }
                continue;
            }

            $normalized[$key] = $value === null ? 'null' : (string) $value;
        }

        if (!array_key_exists('additional_info', $normalized)) {
            $normalized['additional_info'] = [];
        }

        return $normalized;
    }
}
