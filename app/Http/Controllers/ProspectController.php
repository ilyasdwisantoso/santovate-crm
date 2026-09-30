<?php
namespace App\Http\Controllers;
use App\Http\Requests\ProspectRequest;
use App\Http\Resources\ProspectResource;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\ProspectAssignmentHistory;
use App\Models\User;
use App\Services\BusinessConfigurationService;
use App\Services\ProspectStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller {
    public function index(Request $request,BusinessConfigurationService $cfg): Response {
        $q=Prospect::query()->visibleTo($request->user())->with(['assignedUser','products']);
        if($s=trim((string)$request->query('q')))$q->where(fn($x)=>$x->where('company_name','like',"%$s%")->orWhere('city','like',"%$s%")->orWhere('contact_name','like',"%$s%")->orWhere('email','like',"%$s%")->orWhere('service','like',"%$s%"));
        if($request->filled('priority'))$q->where('priority',$request->priority);
        if($request->filled('status'))$q->where('status',$request->status);
        if($request->filled('qualification_status'))$q->where('qualification_status',$request->qualification_status);
        if($request->filled('assigned_to')&&$request->user()->isAdmin())$request->assigned_to==='unassigned'?$q->whereNull('assigned_to'):$q->where('assigned_to',$request->assigned_to);
        if($request->query('followup')==='due')$q->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<=',now()->endOfDay());
        $pros=$q->orderByDesc('total_score')->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $pros->setCollection($pros->getCollection()->map(fn($p)=>(new ProspectResource($p))->resolve()));
        return Inertia::render('Prospects/Index',['prospects'=>$pros,'filters'=>$request->only(['q','priority','status','qualification_status','assigned_to','followup']),'statuses'=>$cfg->statusLabels($request->user()->organization),'qualificationStatuses'=>$this->qualificationStatuses(),'priorities'=>Prospect::PRIORITIES,'salesUsers'=>$this->salesUsers($request)]);
    }
    public function create(Request $request,BusinessConfigurationService $cfg): Response {
        return Inertia::render('Prospects/Form',['prospect'=>['fit_score'=>0,'pain_score'=>0,'contact_score'=>0,'status'=>'baru','qualification_status'=>'new','tracking_portal'=>null,'estimated_deal_value'=>0,'estimated_budget'=>'','probability'=>10,'assigned_to'=>$request->user()->isAdmin()?null:$request->user()->id,'product_ids'=>[]],'mode'=>'create','statuses'=>$cfg->statusLabels($request->user()->organization),'qualificationStatuses'=>$this->qualificationStatuses(),'salesUsers'=>$this->salesUsers($request),'products'=>Product::where('organization_id',$request->user()->organization_id)->where('is_active',true)->get()]);
    }
    public function store(ProspectRequest $request): RedirectResponse {
        $data=$request->validated();
        if(!$request->boolean('force_duplicate')){
            $dupes=$this->findDuplicates($request,$data);
            if($dupes->isNotEmpty()) return back()->withInput()->withErrors(['duplicate'=>'Potensi duplikat ditemukan: '.$dupes->pluck('company_name')->take(3)->join(', ').'. Centang "Tetap simpan" jika memang berbeda.']);
        }
        $productIds=$data['product_ids']??[];$opt=$request->boolean('whatsapp_opt_in');
        unset($data['product_ids'],$data['whatsapp_opt_in'],$data['force_duplicate'],$data['assignment_reason']);
        $data['organization_id']=$request->user()->organization_id;$data['created_by']=$request->user()->id;$data['assigned_to']=$request->user()->isAdmin()?($data['assigned_to']??null):$request->user()->id;
        if($opt)$data['whatsapp_opt_in_at']=now();$status=$data['status'];unset($data['status']);
        $p=Prospect::create([...$data,'status'=>'baru']);
        if($productIds)$p->products()->sync(collect($productIds)->mapWithKeys(fn($id)=>[$id=>['is_primary'=>false]])->all());
        ProspectActivity::create(['prospect_id'=>$p->id,'user_id'=>$request->user()->id,'type'=>'note','title'=>'Prospek dibuat','description'=>'Prospek ditambahkan ke CRM.','occurred_at'=>now()]);
        if($p->assigned_to) ProspectAssignmentHistory::create(['organization_id'=>$p->organization_id,'prospect_id'=>$p->id,'from_user_id'=>null,'to_user_id'=>$p->assigned_to,'changed_by'=>$request->user()->id,'reason'=>'Initial assignment']);
        if($status!=='baru')app(ProspectStageService::class)->transition($p,$status,$request->user(),'Form prospek');
        return redirect()->route('prospects.show',$p)->with('success','Prospek berhasil ditambahkan.');
    }
    public function show(Request $request,Prospect $prospect,BusinessConfigurationService $cfg): Response {
        $p=$this->visible($request,$prospect);
        $p->load(['assignedUser','creator','activities.user','products','opportunities.owner','quotations','deals','assignmentHistories.fromUser','assignmentHistories.toUser','assignmentHistories.changer']);
        return Inertia::render('Prospects/Show',['prospect'=>(new ProspectResource($p))->resolve(),'statuses'=>$cfg->statusLabels($request->user()->organization),'qualificationStatuses'=>$this->qualificationStatuses(),'activityTypes'=>ProspectActivity::TYPES]);
    }
    public function edit(Request $request,Prospect $prospect,BusinessConfigurationService $cfg): Response {
        $p=$this->visible($request,$prospect);$p->load(['assignedUser','products']);$payload=(new ProspectResource($p))->resolve();$payload['product_ids']=$p->products->pluck('id');
        return Inertia::render('Prospects/Form',['prospect'=>$payload,'mode'=>'edit','statuses'=>$cfg->statusLabels($request->user()->organization),'qualificationStatuses'=>$this->qualificationStatuses(),'salesUsers'=>$this->salesUsers($request),'products'=>Product::where('organization_id',$request->user()->organization_id)->where('is_active',true)->get()]);
    }
    public function update(ProspectRequest $request,Prospect $prospect,ProspectStageService $stages): RedirectResponse {
        $p=$this->visible($request,$prospect);$data=$request->validated();$oldAssigned=$p->assigned_to;
        $productIds=$data['product_ids']??null;$reason=$data['assignment_reason']??null;
        unset($data['product_ids'],$data['assignment_reason'],$data['force_duplicate']);
        if($request->has('whatsapp_opt_in'))$data['whatsapp_opt_in_at']=$request->boolean('whatsapp_opt_in')?now():null;unset($data['whatsapp_opt_in']);
        $status=$data['status'];unset($data['status'],$data['actual_deal_value']);if(!$request->user()->isAdmin())unset($data['assigned_to']);
        $p->update($data);if(is_array($productIds))$p->products()->sync($productIds);
        if($request->user()->isAdmin() && $oldAssigned !== $p->assigned_to) ProspectAssignmentHistory::create(['organization_id'=>$p->organization_id,'prospect_id'=>$p->id,'from_user_id'=>$oldAssigned,'to_user_id'=>$p->assigned_to,'changed_by'=>$request->user()->id,'reason'=>$reason?:'Reassignment']);
        $stages->transition($p,$status,$request->user(),'Form prospek');
        return redirect()->route('prospects.show',$p)->with('success','Data prospek berhasil diperbarui.');
    }
    public function updateStatus(Request $request,Prospect $prospect,ProspectStageService $stages): RedirectResponse {$p=$this->visible($request,$prospect);$data=$request->validate(['status'=>['required',Rule::in(array_keys(Prospect::STATUSES))]]);$stages->transition($p,$data['status'],$request->user(),'Pipeline');return back()->with('success','Status pipeline diperbarui.');}
    public function checkDuplicates(Request $request): JsonResponse {
        $data=$request->validate(['company_name'=>['nullable','string'],'city'=>['nullable','string'],'email'=>['nullable','email'],'phone'=>['nullable','string'],'exclude_id'=>['nullable','integer']]);
        $rows=$this->findDuplicates($request,$data,$data['exclude_id']??null)->take(8)->map(fn($p)=>['id'=>$p->id,'company_name'=>$p->company_name,'city'=>$p->city,'email'=>$p->email,'phone'=>$p->phone,'assigned_to'=>$p->assignedUser?->name,'url'=>route('prospects.show',$p)])->values();
        return response()->json(['duplicates'=>$rows]);
    }
    public function destroy(Request $request,Prospect $prospect): RedirectResponse {abort_unless($request->user()->isAdmin(),403);$p=$this->visible($request,$prospect);abort_if($p->deals()->exists(),422,'Prospek yang sudah memiliki Deal tidak dapat dihapus.');$p->delete();return redirect()->route('prospects.index')->with('success','Prospek dihapus.');}
    private function visible(Request $r,Prospect $p): Prospect {abort_unless(Prospect::query()->visibleTo($r->user())->whereKey($p->id)->exists(),403);return$p;}
    private function salesUsers(Request $r): array {if(!$r->user()->isAdmin())return[];return User::where('organization_id',$r->user()->organization_id)->where('is_active',true)->where('role','sales')->orderBy('name')->get(['id','name','email','role'])->toArray();}
    private function qualificationStatuses(): array{return['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','unqualified'=>'Unqualified','nurturing'=>'Nurturing'];}
    private function findDuplicates(Request $r,array $data,?int $exclude=null){$q=Prospect::query()->where('organization_id',$r->user()->organization_id)->with('assignedUser');if($exclude)$q->whereKeyNot($exclude);$company=trim((string)($data['company_name']??''));$city=trim((string)($data['city']??''));$email=strtolower(trim((string)($data['email']??'')));$phone=Prospect::normalizePhone($data['phone']??null);if(!$company&&!$email&&!$phone)return collect();$q->where(function($x)use($company,$city,$email,$phone){if($email)$x->orWhereRaw('LOWER(email)=?',[strtolower($email)]);if($phone)$x->orWhere('phone_normalized',$phone);if($company)$x->orWhere(function($c)use($company,$city){$c->whereRaw('LOWER(company_name)=?',[strtolower($company)]);if($city)$c->whereRaw('LOWER(city)=?',[strtolower($city)]);});});return$q->limit(10)->get();}
}
