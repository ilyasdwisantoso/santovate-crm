<?php
namespace App\Http\Controllers;
use App\Http\Resources\ProspectResource;
use App\Models\Prospect;
use App\Models\User;
use App\Services\BusinessConfigurationService;
use App\Services\FollowUpService;
use App\Services\SalesPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class DashboardController extends Controller {
    public function __invoke(Request $request,SalesPerformanceService $performance,FollowUpService $followUps,BusinessConfigurationService $configs): Response {
        $user=$request->user();$base=Prospect::query()->visibleTo($user);$pipelineConfig=$configs->pipelineFor($user->organization);$pipelineKeys=collect($pipelineConfig)->pluck('key')->all();$open=array_values(array_diff($pipelineKeys,['deal']));$queue=$followUps->queue($user);$followCount=collect($queue)->sum(fn($items)=>$items->count());
        $stats=['total'=>(clone$base)->count(),'priority_high'=>(clone$base)->where('priority','tinggi')->count(),'need_followup'=>$followCount,'meeting_plus'=>(clone$base)->whereIn('status',['meeting','demo','proposal','negosiasi'])->count(),'deals'=>(clone$base)->where('status','deal')->count(),'pipeline_value'=>(float)(clone$base)->whereNotIn('status',['deal','ditolak','tidak_cocok'])->sum('estimated_deal_value')];
        $pipeline=collect($pipelineConfig)->map(fn($stage)=>['key'=>$stage['key'],'label'=>$stage['label'],'count'=>(clone$base)->where('status',$stage['key'])->count()])->values();
        $scheduled=(clone$base)->with('assignedUser')->whereIn('status',$open)->whereNotNull('next_follow_up_at')->orderBy('next_follow_up_at')->limit(6)->get();$top=(clone$base)->with('assignedUser')->orderByDesc('total_score')->orderByDesc('estimated_deal_value')->limit(6)->get();$month=now()->month;$year=now()->year;$salesPerformance=!$user->isAdmin()?$performance->forUser($user,$year,$month):null;$team=$user->isAdmin()?User::where('organization_id',$user->organization_id)->where('role','sales')->where('is_active',true)->orderBy('name')->get()->map(fn($sales)=>['user'=>['id'=>$sales->id,'name'=>$sales->name],'performance'=>$performance->forUser($sales,$year,$month)])->values():[];
        return Inertia::render('Dashboard/Index',['stats'=>$stats,'pipeline'=>$pipeline,'followUps'=>ProspectResource::collection($scheduled)->resolve(),'topProspects'=>ProspectResource::collection($top)->resolve(),'salesPerformance'=>$salesPerformance,'teamPerformance'=>$team]);
    }
}
