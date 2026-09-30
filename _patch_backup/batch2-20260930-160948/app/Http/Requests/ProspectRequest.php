<?php
namespace App\Http\Requests;
use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProspectRequest extends FormRequest {
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array {
        $org=$this->user()?->organization_id;
        return [
            'company_name'=>['required','string','max:255'],'website'=>['nullable','url','max:255'],'city'=>['nullable','string','max:255'],
            'service'=>['nullable','string','max:255'],'route'=>['nullable','string','max:255'],'company_size'=>['nullable','string','max:255'],
            'contact_name'=>['nullable','string','max:255'],'contact_position'=>['nullable','string','max:255'],
            'decision_maker_name'=>['nullable','string','max:255'],'decision_maker_position'=>['nullable','string','max:255'],
            'phone'=>['nullable','string','max:100'],'email'=>['nullable','email','max:255'],'current_system'=>['nullable','string','max:255'],
            'tracking_portal'=>['nullable',Rule::in(['1','0',1,0,true,false])],'pain_hypothesis'=>['nullable','string','max:4000'],
            'fit_score'=>['required','integer','between:0,3'],'pain_score'=>['required','integer','between:0,3'],'contact_score'=>['required','integer','between:0,3'],
            'status'=>['required',Rule::in(array_keys(Prospect::STATUSES))],
            'qualification_status'=>['required',Rule::in(['new','contacted','qualified','unqualified','nurturing'])],
            'last_contact_at'=>['nullable','date'],'next_follow_up_at'=>['nullable','date'],'source_name'=>['nullable','string','max:255'],
            'source_url'=>['nullable','url','max:2000'],'notes'=>['nullable','string','max:10000'],
            'estimated_deal_value'=>['nullable','numeric','min:0','max:999999999999999'],'estimated_budget'=>['nullable','numeric','min:0','max:999999999999999'],
            'expected_timeline'=>['nullable','string','max:255'],'target_go_live'=>['nullable','date'],'urgency'=>['nullable',Rule::in(['low','medium','high'])],
            'probability'=>['nullable','integer','between:0,100'],'next_action'=>['nullable','string','max:255'],
            'assigned_to'=>['nullable',Rule::exists('users','id')->where(fn($q)=>$q->where('organization_id',$org)->where('role','sales')->where('is_active',true))],
            'assignment_reason'=>['nullable','string','max:255'],
            'product_ids'=>['nullable','array'],'product_ids.*'=>['integer',Rule::exists('products','id')->where(fn($q)=>$q->where('organization_id',$org))],
            'whatsapp_opt_in'=>['nullable','boolean'],'force_duplicate'=>['nullable','boolean'],
        ];
    }
}
