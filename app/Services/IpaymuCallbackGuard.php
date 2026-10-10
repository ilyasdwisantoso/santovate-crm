<?php

namespace App\Services;

/**
 * Business checks applied AFTER IpaymuCallbackVerifier authenticates the HMAC.
 * No database writes or calls to iPaymu are performed here.
 */
class IpaymuCallbackGuard
{
    private const REFERENCE_KEYS = ['reference_id', 'referenceId', 'reference'];

    public function reference(array $payload): string
    {
        foreach (self::REFERENCE_KEYS as $key) {
            if (!isset($payload[$key]) || !is_string($payload[$key])) {
                continue;
            }
            $reference = trim($payload[$key]);
            if ($reference !== '' && strlen($reference) <= 160) {
                return $reference;
            }
        }
        return '';
    }

    /** Returns a short, non-sensitive reason, or null when the signed payload matches. */
    public function paymentIssue(array $payload, string $reference, float $expectedAmount, ?float $receivedAmount): ?string
    {
        if ($reference === '' || $this->reference($payload) !== $reference) {
            return 'Callback reference does not match the payment.';
        }

        foreach (self::REFERENCE_KEYS as $key) {
            if (array_key_exists($key, $payload) &&
                (!is_string($payload[$key]) || trim($payload[$key]) !== $reference)) {
                return 'Callback contains conflicting reference identifiers.';
            }
        }

        if ($receivedAmount === null || !is_finite($receivedAmount) || $receivedAmount <= 0) {
            return 'Callback payment amount is missing or invalid.';
        }

        if ($expectedAmount <= 0 || abs($receivedAmount - $expectedAmount) > 0.01) {
            return 'Callback payment amount does not match the order.';
        }

        return null;
    }

    /** A payment must not be used to activate resources for a different tenant. */
    public function storedAmountsMatch(float $paymentAmount, float $orderAmount): bool
    {
        return $paymentAmount > 0 && $orderAmount > 0 && abs($paymentAmount - $orderAmount) <= 0.01;
    }

    public function organizationMatches(int $paymentOrganizationId, ?int $relatedOrganizationId): bool
    {
        return $paymentOrganizationId > 0 && $relatedOrganizationId !== null
            && $paymentOrganizationId === $relatedOrganizationId;
    }

    public function isPaid(array $payload): bool
    {
        $status = strtolower(trim((string) ($payload['status'] ?? $payload['trx_status'] ?? '')));
        $code = (int) ($payload['transaction_status_code'] ?? $payload['status_code'] ?? 0);
        return in_array($status, ['success', 'paid', 'berhasil', 'completed'], true)
            || in_array($code, [1, 6], true);
    }

    public function failedStatus(array $payload): ?string
    {
        $status = strtolower(trim((string) ($payload['status'] ?? $payload['trx_status'] ?? '')));
        if (in_array($status, ['cancelled', 'canceled'], true)) return 'cancelled';
        if ($status === 'expired') return 'expired';
        if (in_array($status, ['failed', 'failure'], true)) return 'failed';
        return null;
    }
}
