<?php

namespace Tests\Unit;

use App\Http\Controllers\WhatsAppWebhookController;
use App\Services\ProspectStageService;
use Illuminate\Http\Request;
use Tests\TestCase;

class WhatsAppWebhookSecurityTest extends TestCase
{
    private function inboundRequest(string $json, ?string $signature = null): Request
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) $headers['HTTP_X_HUB_SIGNATURE_256'] = $signature;

        return Request::create('/api/whatsapp/webhook', 'POST', [], [], [], $headers, $json);
    }

    private function controller(): WhatsAppWebhookController
    {
        return app(WhatsAppWebhookController::class);
    }

    public function test_verification_rejects_empty_configured_token_even_when_request_token_is_empty(): void
    {
        config()->set('santovate.whatsapp.verify_token', '');
        $request = Request::create('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=example', 'GET');
        $this->assertSame(403, $this->controller()->verify($request)->getStatusCode());
    }

    public function test_verification_accepts_only_matching_nonempty_token(): void
    {
        config()->set('santovate.whatsapp.verify_token', 'development-only-verify-secret');
        $request = Request::create('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=development-only-verify-secret&hub.challenge=12345', 'GET');
        $response = $this->controller()->verify($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('12345', $response->getContent());
    }

    public function test_incoming_request_fails_closed_when_app_secret_is_missing(): void
    {
        config()->set('santovate.whatsapp.app_secret', '');
        $json = '{"entry":[]}';
        $response = $this->controller()->receive($this->inboundRequest($json), app(ProspectStageService::class));
        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_incoming_request_rejects_invalid_or_missing_signature(): void
    {
        config()->set('santovate.whatsapp.app_secret', 'test-app-secret');
        $json = '{"entry":[]}';
        $stages = app(ProspectStageService::class);
        $this->assertSame(401, $this->controller()->receive($this->inboundRequest($json), $stages)->getStatusCode());
        $this->assertSame(401, $this->controller()->receive($this->inboundRequest($json, 'sha256=bad'), $stages)->getStatusCode());
    }

    public function test_incoming_request_accepts_correct_hmac_signature(): void
    {
        $secret = 'test-app-secret';
        config()->set('santovate.whatsapp.app_secret', $secret);
        $json = '{"entry":[]}';
        $signature = 'sha256='.hash_hmac('sha256', $json, $secret);
        $response = $this->controller()->receive($this->inboundRequest($json, $signature), app(ProspectStageService::class));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('EVENT_RECEIVED', $response->getContent());
    }
}
