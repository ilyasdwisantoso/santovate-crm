<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProspectRequest;
use App\Http\Resources\ProspectResource;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Services\ProspectStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Prospect::query()->visibleTo($request->user())->with('assignedUser');
        if ($search = trim((string)$request->query('q'))) {
            $query->where(fn ($q) => $q->where('company_name','like',"%{$search}%")
                ->orWhere('city','like',"%{$search}%")->orWhere('contact_name','like',"%{$search}%")
                ->orWhere('email','like',"%{$search}%")->orWhere('service','like',"%{$search}%"));
        }
        if ($request->filled('priority')) $query->where('priority',$request->priority);
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('assigned_to') && $request->user()->isAdmin()) {
            $request->assigned_to === 'unassigned' ? $query->whereNull('assigned_to') : $query->where('assigned_to',$request->assigned_to);
        }
        if ($request->filled('import_batch')) $query->where('import_batch_id',$request->integer('import_batch'));
        if ($request->query('followup') === 'due') $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<=',now()->endOfDay());

        $prospects = $query->orderByDesc('total_score')->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $prospects->setCollection($prospects->getCollection()->map(fn ($p) => (new ProspectResource($p))->resolve()));

        return Inertia::render('Prospects/Index', [
            'prospects'=>$prospects,
            'filters'=>$request->only(['q','priority','status','assigned_to','followup','import_batch']),
            'statuses'=>Prospect::STATUSES,
            'priorities'=>Prospect::PRIORITIES,
            'salesUsers'=>$this->salesUsers($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Prospects/Form', [
            'prospect'=>[
                'fit_score'=>0,'pain_score'=>0,'contact_score'=>0,'status'=>'baru','tracking_portal'=>null,
                'estimated_deal_value'=>0,'assigned_to'=>$request->user()->isAdmin()?null:$request->user()->id,
            ],
            'mode'=>'create','statuses'=>Prospect::STATUSES,'salesUsers'=>$this->salesUsers($request),
        ]);
    }

    public function store(ProspectRequest $request): RedirectResponse
    {
        $data=$request->validated();
        $data['created_by']=$request->user()->id;
        $data['assigned_to']=$request->user()->isAdmin()?($data['assigned_to']??null):$request->user()->id;
        $status=$data['status']; unset($data['status']);
        $prospect=Prospect::create([...$data,'status'=>'baru']);
        ProspectActivity::create(['prospect_id'=>$prospect->id,'user_id'=>$request->user()->id,'type'=>'note','title'=>'Prospek dibuat','description'=>'Prospek ditambahkan ke CRM.','occurred_at'=>now()]);
        if ($status !== 'baru') app(ProspectStageService::class)->transition($prospect,$status,$request->user(),'Form prospek');
        return redirect()->route('prospects.show',$prospect)->with('success','Prospek berhasil ditambahkan.');
    }

    public function show(Request $request, Prospect $prospect): Response
    {
        $prospect=$this->visibleProspect($request,$prospect);
        $prospect->load(['assignedUser','creator','activities.user']);
        return Inertia::render('Prospects/Show', [
            'prospect'=>(new ProspectResource($prospect))->resolve(),
            'statuses'=>Prospect::STATUSES,
            'activityTypes'=>ProspectActivity::TYPES,
        ]);
    }

    public function edit(Request $request, Prospect $prospect): Response
    {
        $prospect=$this->visibleProspect($request,$prospect); $prospect->load('assignedUser');
        return Inertia::render('Prospects/Form', [
            'prospect'=>(new ProspectResource($prospect))->resolve(),
            'mode'=>'edit','statuses'=>Prospect::STATUSES,'salesUsers'=>$this->salesUsers($request),
        ]);
    }

    public function update(ProspectRequest $request, Prospect $prospect, ProspectStageService $stages): RedirectResponse
    {
        $prospect=$this->visibleProspect($request,$prospect);
        $data=$request->validated(); $newStatus=$data['status']; unset($data['status']);
        if (!$request->user()->isAdmin()) unset($data['assigned_to']);
        $prospect->update($data);
        $stages->transition($prospect,$newStatus,$request->user(),'Form prospek');
        return redirect()->route('prospects.show',$prospect)->with('success','Data prospek berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Prospect $prospect, ProspectStageService $stages): RedirectResponse
    {
        $prospect=$this->visibleProspect($request,$prospect);
        $data=$request->validate(['status'=>['required',Rule::in(array_keys(Prospect::STATUSES))]]);
        $stages->transition($prospect,$data['status'],$request->user(),'Pipeline');
        return back()->with('success','Status pipeline diperbarui.');
    }

    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(),403);
        $prospect->delete();
        return redirect()->route('prospects.index')->with('success','Prospek dihapus.');
    }

    private function visibleProspect(Request $request, Prospect $prospect): Prospect
    {
        abort_unless(Prospect::query()->visibleTo($request->user())->whereKey($prospect->id)->exists(),403);
        return $prospect;
    }

    private function salesUsers(Request $request): array
    {
        if (!$request->user()->isAdmin()) return [];
        return User::query()->where('is_active',true)->where('role','sales')->orderBy('name')->get(['id','name','email','role'])->toArray();
    }
}
