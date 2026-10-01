<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements): Response
    {
        $organization = $request->user()->organization;
        $users = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->withCount('assignedProspects')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users'=>$users,
            'entitlements'=>$organization ? $entitlements->snapshot($organization) : null,
        ]);
    }

    public function create(Request $request, EntitlementService $entitlements): Response
    {
        $organization = $request->user()->organization;
        return Inertia::render('Admin/Users/Form', [
            'user'=>['role'=>'sales','is_active'=>true],
            'mode'=>'create',
            'entitlements'=>$organization ? $entitlements->snapshot($organization) : null,
        ]);
    }

    public function store(Request $request, EntitlementService $entitlements): RedirectResponse
    {
        $organizationId = (int)$request->user()->organization_id;
        abort_unless($organizationId, 409, 'Akun admin belum terhubung ke organization.');
        $data = $this->validateUser($request);
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($request,$entitlements,$organizationId,$data) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            if ($data['is_active']) $entitlements->assertCanConsume($organization,'users',1);
            User::create([
                ...$data,
                'organization_id'=>$organization->id,
                'is_platform_admin'=>false,
            ]);
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun user berhasil dibuat.');
    }

    public function edit(Request $request, User $user, EntitlementService $entitlements): Response
    {
        $this->assertEditableUser($request, $user);
        return Inertia::render('Admin/Users/Form', [
            'user'=>$user->only(['id','name','email','role','is_active']),
            'mode'=>'edit',
            'entitlements'=>$request->user()->organization ? $entitlements->snapshot($request->user()->organization) : null,
        ]);
    }

    public function update(Request $request, User $user, EntitlementService $entitlements): RedirectResponse
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

        DB::transaction(function () use ($request,$user,$data,$entitlements) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($request->user()->organization_id);
            if (!$user->is_active && $data['is_active']) $entitlements->assertCanConsume($organization,'users',1);
            $user->update($data);
        });

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
