<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Inertia\Inertia;
use Inertia\Response;

class AcceptTeamInvitationController extends Controller
{
    public function show(string $token, TeamInvitationService $service): Response
    {
        $invitation = $service->findRedeemable($token);
        if (!$invitation) {
            return Inertia::render('Auth/InvitationUnavailable');
        }
        return Inertia::render('Auth/AcceptTeamInvitation', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization_name' => $invitation->organization?->name ?? 'Workspace CRM',
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function accept(Request $request, string $token, TeamInvitationService $service): RedirectResponse
    {
        if (!$service->findRedeemable($token)) {
            return redirect()->route('team-invitation.show', ['token' => $token]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        try {
            $service->accept($token, trim($data['name']), $data['password']);
        } catch (HttpExceptionInterface $exception) {
            if (in_array($exception->getStatusCode(), [404, 410], true)) {
                return redirect()->route('team-invitation.show', ['token' => $token]);
            }
            throw $exception;
        }
        return redirect()->route('login')->with('success', 'Akun berhasil diaktifkan. Silakan login menggunakan email undangan.');
    }
}
