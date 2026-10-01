<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntitlementFeature
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = $request->user()?->organization;
        abort_unless($organization,409,'Workspace belum terhubung ke organization.');
        abort_unless(
            $this->entitlements->allowsFeature($organization,$feature),
            403,
            'Fitur ini tidak termasuk entitlement paket aktif workspace.'
        );
        return $next($request);
    }
}
