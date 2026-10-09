<?php

namespace Tests\Unit;

use App\Services\IpaymuCallbackVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IpaymuCallbackVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        config()->set('santovate.ipaymu.mode', 'production');
        config()->set('santovate.ipaymu.production.callback_secret', 'production-test-secret-v93');
        config()->set('santovate.ipaymu.sandbox.callback_secret', 'sandbox-test-secret-v93');
    }

    private function payload(): array
    {
        return [
            'reference_id' => 'V93-ADDON-TEST-001',
            'status' => 'paid',
            'amount' => '150000',
        ];
    }

    private function signature(array $payload, string $secret): string
    {
        $canonical = $payload;
        $canonical['additional_info'] = [];
        ksort($canonical, SORT_STRING);
        return hash_hmac('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE), $secret);
    }

    private function callbackRequest(array $payload, string $signature): Request
    {
        return Request::create('/api/ipaymu/addon-callback', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function test_production_accepts_only_valid_production_signature(): void
    {
        $payload = $this->payload();
        $verifier = new IpaymuCallbackVerifier();

        $this->assertTrue($verifier->validate($this->callbackRequest(
            $payload, $this->signature($payload, 'production-test-secret-v93')
        )));
        $this->assertFalse($verifier->validate($this->callbackRequest(
            $payload, $this->signature($payload, 'sandbox-test-secret-v93')
        )));
    }

    public function test_sandbox_accepts_sandbox_and_rejects_production(): void
    {
        config()->set('santovate.ipaymu.mode', 'sandbox');
        $payload = $this->payload();
        $verifier = new IpaymuCallbackVerifier();

        $this->assertTrue($verifier->validate($this->callbackRequest(
            $payload, $this->signature($payload, 'sandbox-test-secret-v93')
        )));
        $this->assertFalse($verifier->validate($this->callbackRequest(
            $payload, $this->signature($payload, 'production-test-secret-v93')
        )));
    }

    public function test_modified_payload_is_rejected(): void
    {
        $payload = $this->payload();
        $signature = $this->signature($payload, 'production-test-secret-v93');
        $payload['amount'] = '150001';

        $this->assertFalse((new IpaymuCallbackVerifier())->validate(
            $this->callbackRequest($payload, $signature)
        ));
    }

    public function test_unknown_mode_fails_closed(): void
    {
        config()->set('santovate.ipaymu.mode', 'unexpected');
        $payload = $this->payload();

        $this->assertFalse((new IpaymuCallbackVerifier())->validate($this->callbackRequest(
            $payload, $this->signature($payload, 'production-test-secret-v93')
        )));
    }

    public function test_missing_signature_fails_closed(): void
    {
        $this->assertFalse((new IpaymuCallbackVerifier())->validate($this->callbackRequest(
            $this->payload(), ''
        )));
    }
}
