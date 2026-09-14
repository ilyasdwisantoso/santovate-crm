<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IpaymuService
{
    public function baseUrl(): string
    {
        return config('santovate.ipaymu.mode') === 'production'
            ? rtrim((string)config('santovate.ipaymu.production_url'), '/')
            : rtrim((string)config('santovate.ipaymu.sandbox_url'), '/');
    }

    public function configured(): bool
    {
        return filled(config('santovate.ipaymu.va')) && filled(config('santovate.ipaymu.api_key'));
    }

    public function createCheckout(Payment $payment, array $buyer): array
    {
        if (!$this->configured()) throw new RuntimeException('Kredensial iPaymu belum dikonfigurasi.');

        $payload = [
            'name' => $buyer['name'],
            'phone' => $buyer['phone'] ?? '',
            'email' => $buyer['email'],
            'amount' => (int)$payment->amount,
            'paymentMethod' => $buyer['payment_method'] ?? 'va',
            'paymentChannel' => $buyer['payment_channel'] ?? 'bca',
            'notifyUrl' => route('api.ipaymu.callback'),
            'returnUrl' => route('subscription.payment-result',['reference'=>$payment->reference_id]),
            'cancelUrl' => route('subscription.checkout'),
            'referenceId' => $payment->reference_id,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $response = Http::acceptJson()
            ->withHeaders($this->headers('POST','/api/v2/payment/direct',$json))
            ->withBody($json,'application/json')
            ->post($this->baseUrl().'/api/v2/payment/direct');

        if (!$response->successful()) {
            throw new RuntimeException('iPaymu menolak request checkout: '.$response->status().' '.$response->body());
        }
        $data=$response->json();
        if ((int)($data['Status'] ?? 0) !== 200) {
            throw new RuntimeException((string)($data['Message'] ?? 'Checkout iPaymu gagal.'));
        }
        return $data;
    }

    public function validateCallback(array $payload, ?string $signature): bool
    {
        if (!$signature || !filled(config('santovate.ipaymu.va'))) return false;
        $normalized=$this->normalizeCallback($payload);
        ksort($normalized);
        $json=json_encode($normalized,JSON_UNESCAPED_SLASHES);
        $expected=hash_hmac('sha256',$json,(string)config('santovate.ipaymu.va'));
        return hash_equals(strtolower($expected),strtolower($signature));
    }

    private function headers(string $method, string $path, string $json): array
    {
        $va=(string)config('santovate.ipaymu.va');
        $apiKey=(string)config('santovate.ipaymu.api_key');
        $bodyHash=strtolower(hash('sha256',$json));
        $stringToSign=strtoupper($method).':'.$va.':'.$bodyHash.':'.$apiKey;
        return [
            'va'=>$va,
            'signature'=>hash_hmac('sha256',$stringToSign,$apiKey),
            'timestamp'=>now()->format('YmdHis'),
        ];
    }

    private function normalizeCallback(array $payload): array
    {
        $out=[];
        foreach ($payload as $key=>$value) {
            if (is_array($value)) $out[$key]=$this->normalizeCallback($value);
            elseif (is_bool($value)) $out[$key]=$value;
            elseif ($value === null) $out[$key]=null;
            elseif (is_numeric($value) && !str_starts_with((string)$value,'0')) $out[$key]=$value+0;
            else $out[$key]=(string)$value;
        }
        return $out;
    }
}
