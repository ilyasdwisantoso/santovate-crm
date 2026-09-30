<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\SolutionCatalogItem;
use App\Models\User;
use App\Services\SalesGuideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Opportunity::query()->visibleTo($request->user())->with(['prospect','owner','solutionItems']);
        if ($request->filled('status')) $query->where('status', (string)$request->query('status'));
        if ($request->filled('stage')) $query->where('stage', (string)$request->query('stage'));
        if ($request->filled('q')) {
            $q = trim((string)$request->query('q'));
            $query->where(fn ($x) => $x->where('name','like',"%{$q}%")->orWhereHas('prospect', fn ($p) => $p->where('company_name','like',"%{$q}%")));
        }
        $items = $query->orderByRaw("FIELD(status,'open','won','lost')")->orderByDesc('expected_value')->paginate(20)->withQueryString();
        $items->through(fn ($o) => $this->payload($o));
        return Inertia::render('Opportunities/Index', [
            'opportunities'=>$items,'filters'=>$request->only(['q','status','stage']),
            'stages'=>Opportunity::STAGES,'statuses'=>Opportunity::STATUSES,'lostReasons'=>Opportunity::LOST_REASONS,
        ]);
    }

    public function create(Request $request, SalesGuideService $guide): Response
    {
        $prospect = $request->filled('prospect_id')
            ? Prospect::query()->visibleTo($request->user())->findOrFail((int)$request->prospect_id)
            : null;

        return Inertia::render('Opportunities/Form', [
            'mode'=>'create',
            'opportunity'=>[
                'prospect_id'=>$prospect?->id,'owner_id'=>$prospect?->assigned_to ?: ($request->user()->isAdmin()?null:$request->user()->id),
                'name'=>$prospect ? 'Project '.$prospect->company_name : '', 'stage'=>'qualification','status'=>'open',
                'business_problem'=>'','current_process'=>'','required_solution'=>'','required_features'=>'',
                'expected_value'=>(float)($prospect?->estimated_deal_value ?? 0),'budget'=>$prospect?->estimated_budget ? (float)$prospect->estimated_budget : '',
                'probability'=>(int)($prospect?->probability ?? 10),'target_go_live'=>$prospect?->target_go_live?->format('Y-m-d'),
                'decision_maker'=>$prospect?->decision_maker_name,'decision_process'=>'','urgency'=>$prospect?->urgency,
                'next_action'=>$prospect?->next_action,'next_follow_up_at'=>$prospect?->next_follow_up_at?->format('Y-m-d\TH:i'),
                'solution_item_ids'=>[],
            ],
            'prospects'=>$this->prospects($request),'salesUsers'=>$this->salesUsers($request),
            'stages'=>Opportunity::STAGES,'statuses'=>Opportunity::STATUSES,'lostReasons'=>Opportunity::LOST_REASONS,
            'solutionCatalog'=>$guide->solutions($request->user(), true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateOpportunity($request);
        $solutionIds = $data['solution_item_ids'] ?? [];
        unset($data['solution_item_ids']);
        $prospect = Prospect::query()->visibleTo($request->user())->findOrFail($data['prospect_id']);
        $data['organization_id']=$request->user()->organization_id;
        $data['created_by']=$request->user()->id;
        $data['owner_id']=$request->user()->isAdmin()?($data['owner_id'] ?? $prospect->assigned_to):$request->user()->id;
        $this->normalizeClosedState($data);
        $opportunity = Opportunity::create($data);
        $opportunity->solutionItems()->sync($solutionIds);
        return redirect()->route('opportunities.show',$opportunity)->with('success','Opportunity berhasil dibuat.');
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $o=$this->visible($request,$opportunity);
        $o->load(['prospect.assignedUser','owner','quotations.items','deals','solutionItems']);
        return Inertia::render('Opportunities/Show', [
            'opportunity'=>$this->payload($o, true),'stages'=>Opportunity::STAGES,
            'statuses'=>Opportunity::STATUSES,'lostReasons'=>Opportunity::LOST_REASONS,
        ]);
    }

    public function edit(Request $request, Opportunity $opportunity, SalesGuideService $guide): Response
    {
        $o=$this->visible($request,$opportunity);
        $o->load('solutionItems');
        return Inertia::render('Opportunities/Form', [
            'mode'=>'edit','opportunity'=>$this->payload($o),'prospects'=>$this->prospects($request),'salesUsers'=>$this->salesUsers($request),
            'stages'=>Opportunity::STAGES,'statuses'=>Opportunity::STATUSES,'lostReasons'=>Opportunity::LOST_REASONS,
            'solutionCatalog'=>$guide->solutions($request->user(), true),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $o=$this->visible($request,$opportunity);
        $data=$this->validateOpportunity($request,$o);
        $solutionIds = $data['solution_item_ids'] ?? [];
        unset($data['solution_item_ids']);
        Prospect::query()->visibleTo($request->user())->findOrFail($data['prospect_id']);
        if (!$request->user()->isAdmin()) $data['owner_id']=$request->user()->id;
        $this->normalizeClosedState($data, $o);
        $o->update($data);
        $o->solutionItems()->sync($solutionIds);
        return redirect()->route('opportunities.show',$o)->with('success','Opportunity diperbarui.');
    }

    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $o=$this->visible($request,$opportunity);
        abort_if($o->quotations()->exists() || $o->deals()->exists(), 422, 'Opportunity yang sudah memiliki quotation/deal tidak dapat dihapus.');
        $o->delete();
        return redirect()->route('opportunities.index')->with('success','Opportunity dihapus.');
    }

    private function validateOpportunity(Request $request, ?Opportunity $opportunity=null): array
    {
        $org=$request->user()->organization_id;
        return $request->validate([
            'prospect_id'=>['required',Rule::exists('prospects','id')->where(fn($q)=>$q->where('organization_id',$org))],
            'owner_id'=>['nullable',Rule::exists('users','id')->where(fn($q)=>$q->where('organization_id',$org)->where('is_active',true)->where('role','sales'))],
            'name'=>['required','string','max:255'],'status'=>['required',Rule::in(array_keys(Opportunity::STATUSES))],'stage'=>['required',Rule::in(array_keys(Opportunity::STAGES))],
            'business_problem'=>['nullable','string','max:10000'],'current_process'=>['nullable','string','max:10000'],'required_solution'=>['nullable','string','max:10000'],'required_features'=>['nullable','string','max:10000'],
            'estimated_users'=>['nullable','integer','min:1','max:100000'],'budget'=>['nullable','numeric','min:0'],'expected_value'=>['required','numeric','min:0'],'probability'=>['required','integer','between:0,100'],
            'target_go_live'=>['nullable','date'],'decision_maker'=>['nullable','string','max:255'],'decision_process'=>['nullable','string','max:5000'],'urgency'=>['nullable',Rule::in(array_keys(Opportunity::URGENCIES))],
            'next_action'=>['nullable','string','max:255'],'next_follow_up_at'=>['nullable','date'],'lost_reason'=>['nullable',Rule::in(array_keys(Opportunity::LOST_REASONS))],'competitor'=>['nullable','string','max:255'],'recontact_at'=>['nullable','date'],
            'solution_item_ids'=>['nullable','array'],'solution_item_ids.*'=>['integer',Rule::exists('solution_catalog_items','id')->where(fn($q)=>$q->where('organization_id',$org))],
        ]);
    }

    private function normalizeClosedState(array &$data, ?Opportunity $existing=null): void
    {
        if (($data['status'] ?? 'open') === 'won') { $data['stage']='won'; $data['closed_at']=$existing?->closed_at ?: now(); }
        elseif (($data['status'] ?? 'open') === 'lost') { $data['stage']='lost'; $data['closed_at']=$existing?->closed_at ?: now(); }
        else $data['closed_at']=null;
    }

    private function visible(Request $request, Opportunity $opportunity): Opportunity
    {
        abort_unless(Opportunity::query()->visibleTo($request->user())->whereKey($opportunity->id)->exists(),403);
        return $opportunity;
    }

    private function prospects(Request $request): array
    {
        return Prospect::query()->visibleTo($request->user())->orderBy('company_name')->get(['id','company_name','assigned_to','estimated_deal_value'])->map(fn($p)=>[
            'id'=>$p->id,'company_name'=>$p->company_name,'assigned_to'=>$p->assigned_to,'estimated_deal_value'=>(float)$p->estimated_deal_value,
        ])->all();
    }

    private function salesUsers(Request $request): array
    {
        return User::where('organization_id',$request->user()->organization_id)->where('is_active',true)->where('role','sales')->orderBy('name')->get(['id','name','email','role'])->toArray();
    }

    private function payload(Opportunity $o, bool $deep=false): array
    {
        $data=[
            'id'=>$o->id,'prospect_id'=>$o->prospect_id,'owner_id'=>$o->owner_id,'name'=>$o->name,'status'=>$o->status,'status_label'=>$o->status_label,'stage'=>$o->stage,'stage_label'=>$o->stage_label,
            'business_problem'=>$o->business_problem,'current_process'=>$o->current_process,'required_solution'=>$o->required_solution,'required_features'=>$o->required_features,
            'estimated_users'=>$o->estimated_users,'budget'=>$o->budget!==null?(float)$o->budget:null,'expected_value'=>(float)$o->expected_value,'probability'=>(int)$o->probability,
            'target_go_live'=>$o->target_go_live?->format('Y-m-d'),'decision_maker'=>$o->decision_maker,'decision_process'=>$o->decision_process,'urgency'=>$o->urgency,'next_action'=>$o->next_action,
            'next_follow_up_at'=>$o->next_follow_up_at?->toIso8601String(),'lost_reason'=>$o->lost_reason,'competitor'=>$o->competitor,'recontact_at'=>$o->recontact_at?->toIso8601String(),
            'prospect'=>$o->relationLoaded('prospect') && $o->prospect ? ['id'=>$o->prospect->id,'company_name'=>$o->prospect->company_name,'contact_name'=>$o->prospect->contact_name,'email'=>$o->prospect->email,'phone'=>$o->prospect->phone] : null,
            'owner'=>$o->relationLoaded('owner') && $o->owner ? ['id'=>$o->owner->id,'name'=>$o->owner->name] : null,
            'solution_item_ids'=>$o->relationLoaded('solutionItems') ? $o->solutionItems->pluck('id')->values()->all() : [],
            'solutions'=>$o->relationLoaded('solutionItems') ? $o->solutionItems->map(fn($s)=>['id'=>$s->id,'code'=>$s->code,'name'=>$s->name,'category'=>$s->category,'recommended_price'=>(float)$s->recommended_price])->values() : [],
        ];
        if($deep){
            $data['quotations']=$o->quotations->map(fn($q)=>['id'=>$q->id,'quotation_number'=>$q->quotation_number,'revision_number'=>$q->revision_number,'status'=>$q->status,'status_label'=>$q->status_label,'grand_total'=>(float)$q->grand_total,'valid_until'=>$q->valid_until?->format('Y-m-d')])->values();
            $data['deals']=$o->deals->map(fn($d)=>['id'=>$d->id,'deal_number'=>$d->deal_number,'actual_deal_value'=>(float)$d->actual_deal_value,'closing_date'=>$d->closing_date?->format('Y-m-d')])->values();
        }
        return $data;
    }
}
