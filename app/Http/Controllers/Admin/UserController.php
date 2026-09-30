<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->withCount('assignedProspects')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', ['users'=>$users]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user'=>['role'=>'sales','is_active'=>true],
            'mode'=>'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 409, 'Akun admin belum terhubung ke organization.');

        $plan = $organization->activeSubscription()?->plan;
        $limit = (int) ($plan?->user_limit ?? 3);
        abort_if($organization->users()->count() >= $limit, 422, 'Batas user paket tercapai.');

        $data = $this->validateUser($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['organization_id'] = $organization->id;
        $data['is_platform_admin'] = false;

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Akun user berhasil dibuat.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->assertEditableUser($request, $user);

        return Inertia::render('Admin/Users/Form', [
            'user'=>$user->only(['id','name','email','role','is_active']),
            'mode'=>'edit',
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertEditableUser($request, $user);

        $data = $request->validate([
            'name'=>['required','string','max:255'],
            'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'role'=>['required',Rule::in(['admin','sales','finance'])],
            'is_active'=>['nullable','boolean'],
            'password'=>['nullable','string','min:8','confirmed'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        if (empty($data['password'])) unset($data['password']);

        if ($user->isPlatformAdmin()) {
            $data['role'] = 'admin';
            $data['is_active'] = true;
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    private function validateUser(Request $request): array
    {
        return $request->validate([
            'name'=>['required','string','max:255'],
            'email'=>['required','email','max:255','unique:users,email'],
            'role'=>['required',Rule::in(['admin','sales','finance'])],
            'password'=>['required','string','min:8','confirmed'],
            'is_active'=>['nullable','boolean'],
        ]);
    }

    private function assertEditableUser(Request $request, User $user): void
    {
        abort_unless((int) $user->organization_id === (int) $request->user()->organization_id, 403);
        if ($user->isPlatformAdmin()) abort_unless($request->user()->isPlatformAdmin(), 403);
    }
}
