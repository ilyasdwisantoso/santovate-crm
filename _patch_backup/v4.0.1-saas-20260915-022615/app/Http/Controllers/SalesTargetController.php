<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesTargetRequest;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\SalesPerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesTargetController extends Controller
{
    public function index(Request $request, SalesPerformanceService $performance): Response
    {
        $year=(int)$request->query('year',now()->year);
        $month=(int)$request->query('month',now()->month);
        abort_unless($month>=1 && $month<=12 && $year>=2020 && $year<=2100,422);

        $users=$request->user()->isAdmin()
            ? User::query()->where('role','sales')->where('is_active',true)->orderBy('name')->get()
            : collect([$request->user()]);

        $rows=$users->map(fn ($user) => [
            'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],
            'performance'=>$performance->forUser($user,$year,$month),
        ])->values();

        return Inertia::render('Targets/Index', [
            'rows'=>$rows,'period'=>['year'=>$year,'month'=>$month],
            'canManage'=>$request->user()->isAdmin(),
        ]);
    }

    public function update(SalesTargetRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->role==='sales',422,'Target hanya untuk akun Account Executive.');
        $year=(int)$request->input('year'); $month=(int)$request->input('month');
        abort_unless($month>=1 && $month<=12 && $year>=2020 && $year<=2100,422);
        SalesTarget::updateOrCreate(['user_id'=>$user->id,'year'=>$year,'month'=>$month],$request->validated());
        return back()->with('success','Target Account Executive berhasil diperbarui.');
    }
}
