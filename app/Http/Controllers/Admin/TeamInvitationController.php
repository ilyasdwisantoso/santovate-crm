<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamInvitationController extends Controller
{
    public function store(Request $request, TeamInvitationService $service): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'sales'])],
        ]);
        [$invitation, $token] = $service->invite($request->user(), $data['email'], $data['role']);
        $service->mailInvite($invitation, $token, $request->user()->name);
        return back()->with('success', 'Undangan dikirim ke '.$invitation->email.'.');
    }

    public function resend(Request $request, TeamInvitation $invitation, TeamInvitationService $service): RedirectResponse
    {
        abort_unless((int)$invitation->organization_id === (int)$request->user()->organization_id, 404);
        [$updated, $token] = $service->resend($request->user(), $invitation);
        $service->mailInvite($updated, $token, $request->user()->name);
        return back()->with('success', 'Undangan baru telah dikirim. Tautan sebelumnya tidak berlaku.');
    }

    public function destroy(Request $request, TeamInvitation $invitation, TeamInvitationService $service): RedirectResponse
    {
        abort_unless((int)$invitation->organization_id === (int)$request->user()->organization_id, 404);
        $service->revoke($request->user(), $invitation);
        return back()->with('success', 'Undangan dibatalkan.');
    }
}
