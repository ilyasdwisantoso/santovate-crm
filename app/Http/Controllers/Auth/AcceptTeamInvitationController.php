<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptTeamInvitationController extends Controller
{
    public function show(string $token, TeamInvitationService $service): Response
    {
        $invitation = $service->findRedeemable($token);
        abort_unless($invitation, 404, 'Undangan tidak ditemukan atau kedaluwarsa.');
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
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $service->accept($token, trim($data['name']), $data['password']);
        return redirect()->route('login')->with('success', 'Akun berhasil diaktifkan. Silakan login menggunakan email undangan.');
    }
}
