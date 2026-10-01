<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\PlatformAuditLog;
use App\Services\PlatformAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformAnalyticsService $analytics): Response
    {
        $data = $analytics->dashboard();
        $data['approval_inbox'] = ApprovalRequest::query()
            ->where('approval_scope','platform')->where('status','pending')
            ->with(['organization:id,name','requester:id,name'])
            ->latest('requested_at')->limit(6)->get()->map(fn($a)=>[
                'id'=>$a->id,'type'=>$a->type,'type_label'=>$a->type_label,'title'=>$a->title,'summary'=>$a->summary,
                'amount'=>$a->amount!==null?(float)$a->amount:null,'organization'=>$a->organization?->name,
                'requester'=>$a->requester?->name,'requested_at'=>$a->requested_at?->toIso8601String(),
            ])->values();
        $data['recent_audit'] = PlatformAuditLog::query()->with('actor:id,name')->latest()->limit(6)->get()->map(fn($log)=>[
            'id'=>$log->id,'action'=>$log->action,'actor'=>$log->actor?->name,'metadata'=>$log->metadata,
            'created_at'=>$log->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Platform/Dashboard',$data);
    }
}
