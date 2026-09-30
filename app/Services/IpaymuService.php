<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IpaymuService
{
    public function baseUrl(): string
    {
        return config('santovate.ipaymu.mode') === 'production'
            ? rtrim((string) config('santovate.ipaymu.production_url'), '/')
            : rtrim((string) config('santovate.ipaymu.sandbox_url'), '/');
    }

    public function configured(): bool
    {
        return filled(config('santovate.ipaymu.va')) && filled(config('santovate.ipaymu.api_key'));
    }

    public function createCheckout(Payment $payment, array $buyer): array
    {
        return $this->createCheckoutRequest(
            (string) $payment->reference_id,
            (float) $payment->amount,
            $buyer,
            route('subscription.payment-result', ['reference'=>$payment->reference_id]),
            route('subscription.checkout')
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

    private function createCheckoutRequest(
        string $reference,
        float $amount,
        array $buyer,
        string $successUrl,
        string $cancelUrl
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
            'notifyUrl'=>route('api.ipaymu.callback'),
            'successUrl'=>$successUrl,
            'cancelUrl'=>$cancelUrl,
            'referenceId'=>$reference,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Payload checkout iPaymu tidak dapat di-encode.');
        }

        $response = Http::acceptJson()
            ->withHeaders($this->headers('POST', '/api/v2/payment/direct', $json))
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
        if (!$signature || !filled(config('santovate.ipaymu.va'))) {
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

        $expected = hash_hmac('sha256', $json, (string) config('santovate.ipaymu.va'));
        return hash_equals(strtolower($expected), strtolower(trim($signature)));
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

    private function headers(string $method, string $path, string $json): array
    {
        $va = (string) config('santovate.ipaymu.va');
        $apiKey = (string) config('santovate.ipaymu.api_key');
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

            // All other callback fields stay strings. This intentionally keeps
            // amount/total/sub_total/fee/payment_no/va as strings.
            $normalized[$key] = $value === null ? 'null' : (string) $value;
        }

        if (!array_key_exists('additional_info', $normalized)) {
            $normalized['additional_info'] = [];
        }

        return $normalized;
    }
}
