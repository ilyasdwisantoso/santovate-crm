<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ApprovalRequest::query()->visibleTo($request->user())
            ->with(['requester:id,name','decider:id,name'])->latest('requested_at');
        if ($request->filled('status')) $query->where('status',$request->string('status'));
        $approvals = $query->paginate(30)->withQueryString();
        $approvals->through(fn($a)=>$this->payload($a));

        return Inertia::render('Approvals/Index',[
            'approvals'=>$approvals,
            'statuses'=>ApprovalRequest::STATUSES,
            'requestTypes'=>collect(ApprovalRequest::TYPES)->only(['final_price','payment_terms','custom_scope','meeting_expense','project_exception']),
            'canDecide'=>$request->user()->isAdmin()
                && !($request->user()->organization?->slug === 'santovate-internal' || (bool)data_get($request->user()->organization?->settings,'internal',false)),
            'prospects'=>Prospect::query()->visibleTo($request->user())->latest('updated_at')->limit(50)->get(['id','company_name']),
        ]);
    }

    public function store(Request $request, ApprovalService $service): RedirectResponse
    {
        $data = $request->validate([
            'type'=>['required',Rule::in(['final_price','payment_terms','custom_scope','meeting_expense','project_exception'])],
            'title'=>['required','string','max:255'],
            'summary'=>['required','string','max:5000'],
            'amount'=>['nullable','numeric','min:0'],
            'prospect_id'=>['nullable','integer'],
        ]);

        $subjectType = null; $subjectId = null;
        if (!empty($data['prospect_id'])) {
            $prospect = Prospect::query()->visibleTo($request->user())->findOrFail((int)$data['prospect_id']);
            $subjectType = Prospect::class; $subjectId = $prospect->id;
        }

        $service->createManual($request->user(),[
            'type'=>$data['type'],'title'=>$data['title'],'summary'=>$data['summary'],'amount'=>$data['amount'] ?? null,
            'subject_type'=>$subjectType,'subject_id'=>$subjectId,
            'metadata'=>['source'=>'manual_request'],
        ]);

        return back()->with('success','Approval request dikirim.');
    }

    public function decide(Request $request, ApprovalRequest $approval, ApprovalService $service): RedirectResponse
    {
        abort_unless($approval->organization_id === $request->user()->organization_id && $approval->approval_scope === 'tenant',403);
        $data = $request->validate([
            'decision'=>['required',Rule::in(['approved','approved_with_conditions','rejected'])],
            'notes'=>['nullable','string','max:5000'],
        ]);
        $service->decide($approval,$request->user(),$data['decision'],$data['notes'] ?? null);
        return back()->with('success','Keputusan approval disimpan.');
    }

    private function payload(ApprovalRequest $a): array
    {
        return [
            'id'=>$a->id,'type'=>$a->type,'type_label'=>$a->type_label,'status'=>$a->status,'status_label'=>$a->status_label,
            'title'=>$a->title,'summary'=>$a->summary,'amount'=>$a->amount!==null?(float)$a->amount:null,
            'metadata'=>$a->metadata,'decision_notes'=>$a->decision_notes,'requester'=>$a->requester?->name,'decider'=>$a->decider?->name,
            'subject_type'=>$a->subject_type,'subject_id'=>$a->subject_id,
            'requested_at'=>$a->requested_at?->toIso8601String(),'decided_at'=>$a->decided_at?->toIso8601String(),
        ];
    }
}
