<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'profile_initials' => $user->profile_initials,
                'whatsapp_signature' => $user->whatsapp_signature,
                'bio' => $user->bio,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'profile_initials' => ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]{1,2}$/'],
            'whatsapp_signature' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($data['profile_initials'])) {
            $data['profile_initials'] = strtoupper($data['profile_initials']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Profile Account Executive berhasil diperbarui.');
    }
}
