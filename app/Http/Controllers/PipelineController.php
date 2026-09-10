<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProspectResource;
use App\Models\Prospect;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $all = Prospect::query()->visibleTo($request->user())->with('assignedUser')
            ->orderByDesc('total_score')->orderByDesc('estimated_deal_value')->get();

        $columns = collect(Prospect::PIPELINE_STATUSES)->map(function ($status) use ($all) {
            $items = $all->where('status',$status)->values();
            return [
                'key'=>$status,'label'=>Prospect::STATUSES[$status],
                'count'=>$items->count(),
                'value'=>(float)$items->sum('estimated_deal_value'),
                'prospects'=>ProspectResource::collection($items)->resolve(),
            ];
        })->values();

        return Inertia::render('Pipeline/Index', [
            'columns'=>$columns,
            'closed'=>[
                'lost'=>$all->whereIn('status',['ditolak','tidak_cocok'])->count(),
                'won'=>$all->where('status','deal')->count(),
            ],
        ]);
    }
}
