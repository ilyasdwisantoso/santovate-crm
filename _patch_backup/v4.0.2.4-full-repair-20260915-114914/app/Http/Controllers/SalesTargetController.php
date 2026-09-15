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
class SalesTargetController extends Controller {
    public function index(Request $request,SalesPerformanceService $performance): Response {$year=(int)$request->query('year',now()->year);$month=(int)$request->query('month',now()->month);abort_unless($month>=1&&$month<=12&&$year>=2020&&$year<=2100,422);$users=$request->user()->isAdmin()?User::where('organization_id',$request->user()->organization_id)->where('role','sales')->where('is_active',true)->orderBy('name')->get():collect([$request->user()]);$rows=$users->map(fn($u)=>['user'=>['id'=>$u->id,'name'=>$u->name,'email'=>$u->email],'performance'=>$performance->forUser($u,$year,$month)])->values();return Inertia::render('Targets/Index',['rows'=>$rows,'period'=>['year'=>$year,'month'=>$month],'canManage'=>$request->user()->isAdmin()]);}
    public function update(SalesTargetRequest $request,User $user): RedirectResponse {abort_unless($user->role==='sales'&&$user->organization_id===$request->user()->organization_id,403);$year=(int)$request->input('year');$month=(int)$request->input('month');SalesTarget::updateOrCreate(['organization_id'=>$request->user()->organization_id,'user_id'=>$user->id,'year'=>$year,'month'=>$month],$request->validated());return back()->with('success','Target Account Executive berhasil diperbarui.');}
}
