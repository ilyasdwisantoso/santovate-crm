<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureSubscriptionActive {
    public function handle(Request $request, Closure $next): Response {
        $user=$request->user();
        if (!$user?->organization_id) return redirect()->route('plans.index')->with('error','Workspace belum memiliki organisasi.');
        if (!$user->organization?->activeSubscription()) return redirect()->route('subscription.checkout')->with('error','Aktifkan subscription untuk membuka workspace CRM.');
        return $next($request);
    }
}
