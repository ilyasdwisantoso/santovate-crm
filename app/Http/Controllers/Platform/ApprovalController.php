<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ApprovalRequest::query()->where('approval_scope','platform')
            ->with(['organization:id,name','requester:id,name,email','decider:id,name']);
        if ($request->filled('status')) $query->where('status',$request->string('status'));
        if ($request->filled('type')) $query->where('type',$request->string('type'));

        $approvals = $query->orderByRaw("CASE WHEN status='pending' THEN 0 ELSE 1 END")
            ->orderByDesc('requested_at')->paginate(30)->withQueryString();
        $approvals->through(fn($a)=>$this->payload($a));

        return Inertia::render('Platform/Approvals/Index',[
            'approvals'=>$approvals,
            'filters'=>$request->only(['status','type']),
            'statuses'=>ApprovalRequest::STATUSES,
            'types'=>ApprovalRequest::TYPES,
        ]);
    }

    public function decide(Request $request, ApprovalRequest $approval, ApprovalService $service): RedirectResponse
    {
        abort_unless($approval->approval_scope === 'platform',404);
        $data = $request->validate([
            'decision'=>['required',Rule::in(['approved','approved_with_conditions','rejected'])],
            'notes'=>['nullable','string','max:5000'],
        ]);
        $service->decide($approval,$request->user(),$data['decision'],$data['notes'] ?? null);
        return back()->with('success','Keputusan approval berhasil disimpan.');
    }

    private function payload(ApprovalRequest $a): array
    {
        return [
            'id'=>$a->id,'scope'=>$a->approval_scope,'type'=>$a->type,'type_label'=>$a->type_label,
            'status'=>$a->status,'status_label'=>$a->status_label,'title'=>$a->title,'summary'=>$a->summary,
            'amount'=>$a->amount!==null?(float)$a->amount:null,'metadata'=>$a->metadata,
            'decision_notes'=>$a->decision_notes,'organization'=>$a->organization?->only(['id','name']),
            'requester'=>$a->requester?->only(['id','name','email']),'decider'=>$a->decider?->only(['id','name']),
            'subject_type'=>$a->subject_type,'subject_id'=>$a->subject_id,
            'requested_at'=>$a->requested_at?->toIso8601String(),'decided_at'=>$a->decided_at?->toIso8601String(),
        ];
    }
}
