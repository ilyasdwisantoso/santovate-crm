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
    public function index(): Response
    {
        $users=User::withCount('assignedProspects')->orderBy('name')->paginate(20);
        return Inertia::render('Admin/Users/Index',['users'=>$users]);
    }
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form',['user'=>['role'=>'sales','is_active'=>true],'mode'=>'create']);
    }
    public function store(Request $request): RedirectResponse
    {
        $data=$this->validateUser($request); $data['is_active']=$request->boolean('is_active');
        User::create($data);
        return redirect()->route('admin.users.index')->with('success','Akun Account Executive berhasil dibuat.');
    }
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form',['user'=>$user->only(['id','name','email','role','is_active']),'mode'=>'edit']);
    }
    public function update(Request $request, User $user): RedirectResponse
    {
        $rules=[
            'name'=>['required','string','max:255'],'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'role'=>['required',Rule::in(['admin','sales'])],'is_active'=>['nullable','boolean'],
            'password'=>['nullable','string','min:8','confirmed'],
        ];
        $data=$request->validate($rules); $data['is_active']=$request->boolean('is_active');
        if (empty($data['password'])) unset($data['password']);
        $user->update($data);
        return redirect()->route('admin.users.index')->with('success','User berhasil diperbarui.');
    }
    private function validateUser(Request $request): array
    {
        return $request->validate([
            'name'=>['required','string','max:255'],'email'=>['required','email','max:255','unique:users,email'],
            'role'=>['required',Rule::in(['admin','sales'])],'password'=>['required','string','min:8','confirmed'],
            'is_active'=>['nullable','boolean'],
        ]);
    }
}
