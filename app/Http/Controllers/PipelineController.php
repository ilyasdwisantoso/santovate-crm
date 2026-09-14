<?php
namespace App\Http\Controllers;
use App\Http\Resources\ProspectResource;
use App\Models\Prospect;
use App\Services\BusinessConfigurationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class PipelineController extends Controller {
    public function __invoke(Request $request,BusinessConfigurationService $configs): Response {
        $all=Prospect::query()->visibleTo($request->user())->with('assignedUser')->orderByDesc('total_score')->orderByDesc('estimated_deal_value')->get();
        $pipeline=$configs->pipelineFor($request->user()->organization);
        $columns=collect($pipeline)->map(function($stage) use($all){$items=$all->where('status',$stage['key'])->values(); return ['key'=>$stage['key'],'label'=>$stage['label'],'count'=>$items->count(),'value'=>(float)$items->sum('estimated_deal_value'),'prospects'=>ProspectResource::collection($items)->resolve()];})->values();
        return Inertia::render('Pipeline/Index',['columns'=>$columns,'closed'=>['lost'=>$all->whereIn('status',['ditolak','tidak_cocok'])->count(),'won'=>$all->where('status','deal')->count()]]);
    }
}
