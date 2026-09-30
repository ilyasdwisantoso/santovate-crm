<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProspectResource extends JsonResource {
    public function toArray(Request $request): array { return [
        'id'=>$this->id,'company_name'=>$this->company_name,'website'=>$this->website,'city'=>$this->city,'service'=>$this->service,'route'=>$this->route,'company_size'=>$this->company_size,
        'contact_name'=>$this->contact_name,'contact_position'=>$this->contact_position,'decision_maker_name'=>$this->decision_maker_name,'decision_maker_position'=>$this->decision_maker_position,
        'phone'=>$this->phone,'phone_normalized'=>$this->phone_normalized,'whatsapp_status'=>$this->whatsapp_status,'whatsapp_verified_at'=>$this->whatsapp_verified_at?->toIso8601String(),
        'whatsapp_opt_in_at'=>$this->whatsapp_opt_in_at?->toIso8601String(),'whatsapp_opt_out_at'=>$this->whatsapp_opt_out_at?->toIso8601String(),'email'=>$this->email,
        'current_system'=>$this->current_system,'tracking_portal'=>$this->tracking_portal,'pain_hypothesis'=>$this->pain_hypothesis,
        'fit_score'=>$this->fit_score,'pain_score'=>$this->pain_score,'contact_score'=>$this->contact_score,'total_score'=>$this->total_score,'priority'=>$this->priority,'priority_label'=>$this->priority_label,
        'status'=>$this->status,'status_label'=>$this->status_label,'qualification_status'=>$this->qualification_status,
        'last_contact_at'=>$this->last_contact_at?->toIso8601String(),'next_follow_up_at'=>$this->next_follow_up_at?->toIso8601String(),'last_outbound_at'=>$this->last_outbound_at?->toIso8601String(),
        'last_customer_reply_at'=>$this->last_customer_reply_at?->toIso8601String(),'follow_up_snoozed_until'=>$this->follow_up_snoozed_until?->toIso8601String(),'follow_up_count'=>(int)($this->follow_up_count??0),
        'last_follow_up_message'=>$this->last_follow_up_message,'last_feedback_at'=>$this->last_feedback_at?->toIso8601String(),'last_feedback_status'=>$this->last_feedback_status,'last_feedback_note'=>$this->last_feedback_note,
        'source_name'=>$this->source_name,'source_url'=>$this->source_url,'notes'=>$this->notes,
        'estimated_deal_value'=>(float)$this->estimated_deal_value,'estimated_budget'=>$this->estimated_budget!==null?(float)$this->estimated_budget:null,'expected_timeline'=>$this->expected_timeline,
        'target_go_live'=>$this->target_go_live?->format('Y-m-d'),'urgency'=>$this->urgency,'probability'=>(int)($this->probability??0),'next_action'=>$this->next_action,
        'actual_deal_value'=>$this->actual_deal_value!==null?(float)$this->actual_deal_value:null,
        'assigned_user'=>$this->whenLoaded('assignedUser',fn()=>$this->assignedUser?['id'=>$this->assignedUser->id,'name'=>$this->assignedUser->name,'email'=>$this->assignedUser->email,'profile_initials'=>$this->assignedUser->profile_initials]:null),
        'creator'=>$this->whenLoaded('creator',fn()=>$this->creator?['id'=>$this->creator->id,'name'=>$this->creator->name]:null),
        'products'=>$this->whenLoaded('products',fn()=>$this->products->map(fn($p)=>['id'=>$p->id,'sku'=>$p->sku,'name'=>$p->name,'variant'=>$p->variant,'price'=>$p->price,'image_url'=>$p->image_url,'is_primary'=>(bool)$p->pivot?->is_primary])->values()),
        'activities'=>$this->whenLoaded('activities',fn()=>$this->activities->map(fn($a)=>[
            'id'=>$a->id,'type'=>$a->type,'type_label'=>$a->type_label??($a::TYPES[$a->type]??$a->type),'title'=>$a->title,'description'=>$a->description,
            'contact_person'=>$a->contact_person,'client_feedback'=>$a->client_feedback,'objection'=>$a->objection,'next_action'=>$a->next_action,
            'next_follow_up_at'=>$a->next_follow_up_at?->toIso8601String(),'occurred_at'=>$a->occurred_at?->toIso8601String(),'user'=>$a->user?['id'=>$a->user->id,'name'=>$a->user->name]:null
        ])),
        'opportunities'=>$this->whenLoaded('opportunities',fn()=>$this->opportunities->map(fn($o)=>[
            'id'=>$o->id,'name'=>$o->name,'status'=>$o->status,'stage'=>$o->stage,'stage_label'=>$o->stage_label,'expected_value'=>(float)$o->expected_value,'probability'=>(int)$o->probability,
            'next_action'=>$o->next_action,'next_follow_up_at'=>$o->next_follow_up_at?->toIso8601String(),'owner'=>$o->owner?['id'=>$o->owner->id,'name'=>$o->owner->name]:null
        ])->values()),
        'quotations'=>$this->whenLoaded('quotations',fn()=>$this->quotations->map(fn($q)=>[
            'id'=>$q->id,'quotation_number'=>$q->quotation_number,'revision_number'=>$q->revision_number,'status'=>$q->status,'status_label'=>$q->status_label,'grand_total'=>(float)$q->grand_total,'valid_until'=>$q->valid_until?->format('Y-m-d')
        ])->values()),
        'deals'=>$this->whenLoaded('deals',fn()=>$this->deals->map(fn($d)=>['id'=>$d->id,'deal_number'=>$d->deal_number,'status'=>$d->status,'actual_deal_value'=>(float)$d->actual_deal_value,'commissionable_value'=>(float)$d->commissionable_value,'closing_date'=>$d->closing_date?->format('Y-m-d')])->values()),
        'assignment_histories'=>$this->whenLoaded('assignmentHistories',fn()=>$this->assignmentHistories->map(fn($h)=>[
            'id'=>$h->id,'reason'=>$h->reason,'created_at'=>$h->created_at?->toIso8601String(),'from_user'=>$h->fromUser?['id'=>$h->fromUser->id,'name'=>$h->fromUser->name]:null,'to_user'=>$h->toUser?['id'=>$h->toUser->id,'name'=>$h->toUser->name]:null,'changed_by'=>$h->changer?['id'=>$h->changer->id,'name'=>$h->changer->name]:null
        ])->values()),
        'created_at'=>$this->created_at?->toIso8601String(),'updated_at'=>$this->updated_at?->toIso8601String(),
    ]; }
}
