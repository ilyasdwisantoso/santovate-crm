<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IpaymuCallbackVerifier
{
    public function validate(Request $request): bool
    {
        $received = strtolower(trim((string) $request->header('X-Signature', '')));
        if ($received === '') {
            $this->logFailure($request, $received, []);
            return false;
        }

        $expectedPrefixes = [];
        foreach ($this->payloadCandidates($request) as $payloadSource => $payload) {
            foreach ($this->canonicalJsonCandidates($payload) as $variant => $json) {
                foreach ($this->callbackSecrets() as $mode => $secret) {
                    $expected = strtolower(hash_hmac('sha256', $json, $secret));
                    $expectedPrefixes["{$payloadSource}:{$variant}:{$mode}"] = substr($expected, 0, 16);

                    if (hash_equals($expected, $received)) {
                        return true;
                    }
                }
            }
        }

        $this->logFailure($request, $received, $expectedPrefixes);
        return false;
    }

    private function payloadCandidates(Request $request): array
    {
        $candidates = ['laravel' => $request->all()];
        $raw = (string) $request->getContent();
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        if ($raw !== '') {
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                $parsed = [];
                parse_str($raw, $parsed);
                if (is_array($parsed) && $parsed !== []) {
                    $candidates['raw_form'] = $parsed;
                }
            } elseif (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $candidates['raw_json'] = $decoded;
                }
            }
        }

        // Deduplicate semantically identical payloads while preserving the
        // safest Laravel-parsed candidate as the first attempt.
        $unique = [];
        $seen = [];
        foreach ($candidates as $source => $payload) {
            $fingerprint = hash('sha256', serialize($payload));
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $unique[$source] = $payload;
        }

        return $unique;
    }

    private function canonicalJsonCandidates(array $payload): array
    {
        $normalized = $this->normalize($payload);
        unset($normalized['signature']);
        ksort($normalized, SORT_STRING);

        // iPaymu's documented callback example uses PHP-style escaped slashes.
        // Their docs also describe slash escaping as optional, so accept the
        // equivalent unescaped JSON representation as a compatibility variant.
        $escaped = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $unescaped = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $candidates = [];
        if (is_string($escaped)) {
            $candidates['escaped_slashes'] = $escaped;
        }
        if (is_string($unescaped) && $unescaped !== $escaped) {
            $candidates['unescaped_slashes'] = $unescaped;
        }

        return $candidates;
    }

    private function normalize(array $payload): array
    {
        $integerFields = ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'];
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
                    continue;
                }

                if ($value === null || $value === '' || $value === '[]') {
                    $normalized[$key] = [];
                    continue;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $normalized[$key] = $decoded;
                        continue;
                    }
                }

                $normalized[$key] = (string) $value;
                continue;
            }

            if (is_bool($value)) {
                // JavaScript String(false) => "false"; PHP (string) false => "".
                // Use the documented JavaScript-compatible representation.
                $normalized[$key] = $value ? 'true' : 'false';
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = $value;
                continue;
            }

            $normalized[$key] = $value === null ? 'null' : (string) $value;
        }

        if (!array_key_exists('additional_info', $normalized)) {
            $normalized['additional_info'] = [];
        }

        return $normalized;
    }

    private function callbackSecrets(): array
    {
        $secrets = [];
        foreach (['sandbox', 'production'] as $mode) {
            $config = (array) config("santovate.ipaymu.{$mode}", []);
            $secret = trim((string) ($config['callback_secret'] ?? ''));
            if ($secret === '') {
                $secret = trim((string) ($config['va'] ?? ''));
            }
            if ($secret !== '') {
                $secrets[$mode] = $secret;
            }
        }

        return $secrets;
    }

    private function logFailure(Request $request, string $received, array $expectedPrefixes): void
    {
        $payload = $request->all();
        $reference = (string) ($payload['reference_id'] ?? $payload['referenceId'] ?? $payload['reference'] ?? $payload['sid'] ?? '');

        Log::warning('iPaymu callback signature mismatch', [
            'reference' => $reference,
            'content_type' => (string) $request->header('Content-Type', ''),
            'gateway_mode' => (string) config('santovate.ipaymu.mode'),
            'received_prefix' => $received !== '' ? substr($received, 0, 16) : null,
            'expected_prefixes' => $expectedPrefixes,
            'payload_keys' => array_keys($payload),
            'raw_body_sha256' => hash('sha256', (string) $request->getContent()),
        ]);
    }
}
