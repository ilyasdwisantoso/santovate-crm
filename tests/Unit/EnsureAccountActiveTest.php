<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureAccountActive;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EnsureAccountActiveTest extends TestCase
{
    private function requestFor(bool $isActive): Request
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => new User(['is_active' => $isActive]));
        $session = new Store('v93-test', new ArraySessionHandler(60));
        $session->start();
        $request->setLaravelSession($session);
        return $request;
    }

    public function test_active_account_can_continue(): void
    {
        Auth::shouldReceive('logout')->never();
        $response = (new EnsureAccountActive())->handle(
            $this->requestFor(true), fn () => response('V93 OK')
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('V93 OK', $response->getContent());
    }

    public function test_deactivated_account_is_logged_out_and_session_cleared(): void
    {
        Auth::shouldReceive('logout')->once();
        $request = $this->requestFor(false);
        $request->session()->put('v93-sensitive', 'must-be-removed');
        $response = (new EnsureAccountActive())->handle(
            $request, fn () => response('MUST NOT RUN')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertFalse($request->session()->has('v93-sensitive'));
    }

    public function test_protected_dashboard_route_has_active_account_guard(): void
    {
        $route = app('router')->getRoutes()->getByName('dashboard');
        $this->assertNotNull($route);
        $this->assertContains('account.active', $route->gatherMiddleware());
    }
}
