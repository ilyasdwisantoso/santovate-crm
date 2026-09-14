<?php

namespace App\Services;

use App\Http\Resources\ProspectResource;
use App\Models\FollowUpTemplate;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FollowUpService
{
    public function __construct(private readonly BusinessConfigurationService $configs) {}

    public function templates(User $user): Collection
    {
        return FollowUpTemplate::query()
            ->where('organization_id',$user->organization_id)
            ->where('is_active',true)
            ->orderBy('id')->get();
    }

    public function noReplyDays(User $user): int
    {
        return (int)($this->templates($user)->firstWhere('trigger_type',FollowUpTemplate::TYPE_NO_REPLY)?->wait_days
            ?? $this->configs->rule($user->organization,'no_reply_days',5));
    }

    public function leadAgeDays(User $user): int
    {
        return (int)($this->templates($user)->firstWhere('trigger_type',FollowUpTemplate::TYPE_LEAD_AGE)?->wait_days
            ?? $this->configs->rule($user->organization,'lead_age_days',3));
    }

    public function queue(User $user): array
    {
        $closed = ['deal','ditolak','tidak_cocok'];
        $base = fn (): Builder => Prospect::query()->visibleTo($user)->with(['assignedUser','products'])
            ->when($closed, fn ($q) => $q->whereNotIn('status',$closed))
            ->where(fn ($q) => $q->whereNull('follow_up_snoozed_until')->orWhere('follow_up_snoozed_until','<=',now()));

        $responseNeeded = $base()->whereNotNull('last_customer_reply_at')
            ->where(fn ($q) => $q->whereNull('last_outbound_at')->orWhereColumn('last_customer_reply_at','>','last_outbound_at'))
            ->orderBy('last_customer_reply_at')->limit(100)->get();
        $responseIds = $responseNeeded->pluck('id');

        $leadAgeDue = $base()->when($responseIds->isNotEmpty(),fn ($q)=>$q->whereNotIn('id',$responseIds))
            ->whereNull('last_outbound_at')->whereNull('last_feedback_at')
            ->where('created_at','<=',now()->subDays($this->leadAgeDays($user)))
            ->orderBy('created_at')->limit(100)->get();
        $leadIds = $leadAgeDue->pluck('id');

        $noReply = $base()->when($responseIds->isNotEmpty(),fn ($q)=>$q->whereNotIn('id',$responseIds))
            ->when($leadIds->isNotEmpty(),fn ($q)=>$q->whereNotIn('id',$leadIds))
            ->whereNotNull('last_outbound_at')->where('last_outbound_at','<=',now()->subDays($this->noReplyDays($user)))
            ->where(fn ($q)=>$q->whereNull('last_customer_reply_at')->orWhereColumn('last_customer_reply_at','<=','last_outbound_at'))
            ->orderBy('last_outbound_at')->limit(100)->get();

        $higher = $responseIds->merge($leadIds)->merge($noReply->pluck('id'))->unique()->values();
        $scheduled = $base()->when($higher->isNotEmpty(),fn ($q)=>$q->whereNotIn('id',$higher))
            ->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<=',now()->endOfDay())
            ->orderBy('next_follow_up_at')->limit(100)->get();

        return compact('responseNeeded','leadAgeDue','noReply','scheduled');
    }

    public function payload(User $user): array
    {
        $q=$this->queue($user); $templates=$this->templates($user);
        $raw=['response_needed'=>$q['responseNeeded'],'lead_age_due'=>$q['leadAgeDue'],'no_reply'=>$q['noReply'],'scheduled'=>$q['scheduled']];
        $select=[
            'response_needed'=>$templates->firstWhere('trigger_type',FollowUpTemplate::TYPE_CUSTOMER_REPLIED),
            'lead_age_due'=>$templates->firstWhere('trigger_type',FollowUpTemplate::TYPE_LEAD_AGE),
            'no_reply'=>$templates->firstWhere('trigger_type',FollowUpTemplate::TYPE_NO_REPLY),
            'scheduled'=>$templates->firstWhere('trigger_type',FollowUpTemplate::TYPE_NO_REPLY),
        ];
        $serialized=[];
        foreach ($raw as $type=>$prospects) {
            $serialized[$type]=$prospects->map(function (Prospect $p) use ($templates,$select,$user,$type) {
                $messages=$templates->mapWithKeys(fn ($t)=>[(string)$t->id=>$this->renderTemplate($t,$p,$user)])->all();
                return [
                    ...(new ProspectResource($p))->resolve(),
                    'queue_type'=>$type,
                    'lead_age_days'=>$p->created_at?max(0,(int)$p->created_at->diffInDays(now())):0,
                    'feedback_required'=>$type==='lead_age_due',
                    'suggested_template_id'=>$select[$type]?->id,
                    'suggested_message'=>$select[$type]?$this->renderTemplate($select[$type],$p,$user):'',
                    'template_messages'=>$messages,
                    'whatsapp_status'=>$p->whatsapp_status,
                    'whatsapp_opted_in'=>(bool)$p->whatsapp_opt_in_at && !$p->whatsapp_opt_out_at,
                ];
            })->values()->all();
        }
        return [
            'queues'=>$serialized,
            'stats'=>array_merge(array_map('count',$serialized),['total'=>collect($serialized)->sum(fn($x)=>count($x))]),
            'templates'=>$templates->map(fn($t)=>[
                'id'=>$t->id,'key'=>$t->key,'name'=>$t->name,'trigger_type'=>$t->trigger_type,'wait_days'=>$t->wait_days,
                'message'=>$t->message,'header_type'=>$t->header_type,'image_url'=>$t->image_url,'meta_template_name'=>$t->meta_template_name,
                'meta_language'=>$t->meta_language,'meta_status'=>$t->meta_status,'meta_category'=>$t->meta_category,'body_parameters'=>$t->body_parameters ?? [],
            ])->values()->all(),
            'noReplyDays'=>$this->noReplyDays($user),'leadAgeDays'=>$this->leadAgeDays($user),
            'whatsappApiReady'=>(bool)$user->organization?->whatsappChannel?->ready(),
        ];
    }

    public function renderTemplate(FollowUpTemplate $template, Prospect $prospect, User $user): string
    {
        $prospect->loadMissing('products','organization');
        $product=$prospect->primaryProduct() ?: Product::query()->where('organization_id',$prospect->organization_id)->where('is_active',true)->first();
        $firstName=trim(explode(' ',trim($user->name))[0]??$user->name);
        $message=strtr($template->message,[
            '{contact_name}'=>$prospect->contact_name ?: 'Bapak/Ibu','{company_name}'=>$prospect->company_name,
            '{business_name}'=>$prospect->organization?->name ?: 'Santovate','{ae_name}'=>$user->name,'{ae_first_name}'=>$firstName,
            '{ae_phone}'=>$user->phone ?: '','{service}'=>$prospect->service ?: 'solusi CRM','{lead_age_days}'=>$prospect->created_at?(string)max(0,(int)$prospect->created_at->diffInDays(now())):'0',
            '{product_name}'=>$product?->name ?: ($prospect->service ?: 'produk'),'{product_variant}'=>$product?->variant ?: '',
            '{product_price}'=>$product?'Rp '.number_format((int)$product->price,0,',','.'):'','{portfolio_url}'=>'portofolio.santovate.com',
        ]);
        $signature=trim((string)$user->whatsapp_signature);
        return $signature!=='' && !str_contains($message,$signature) ? $message."\n\n".$signature : $message;
    }
}
