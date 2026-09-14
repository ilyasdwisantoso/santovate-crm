<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = [];
        $unreadCount = 0;

        if ($user) {
            $unreadCount = $user->unreadNotifications()->count();
            $notifications = $user->notifications()->latest()->limit(8)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'job_title' => $user->job_title,
                    'department' => $user->department,
                    'profile_initials' => $user->profile_initials,
                    'whatsapp_signature' => $user->whatsapp_signature,
                    'role' => $user->role,
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'notifications' => [
                'unread_count' => $unreadCount,
                'items' => $notifications,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
