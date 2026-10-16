<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;
use App\Models\WhatsAppInboundContact;
use App\Models\WhatsAppLeadReview;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsAppLeadConversionService
{
    public function __construct(private readonly ProspectCreationService $creation) {}

    public function convert(User $actor, WhatsAppInboundContact $contact, array $input, ?EntitlementService $entitlements = null): Prospect
    {
        $this->assertTenant($actor, $contact);
        $type = $input['entity_type'] ?? 'business';
        $data = Validator::make($input + ['entity_type'=>$type], [
            'entity_type'=>['required',Rule::in(['business','individual'])],
            'company_name'=>['required_if:entity_type,business','prohibited_unless:entity_type,business','nullable','string','min:2','max:255'],
            'individual_name'=>['required_if:entity_type,individual','prohibited_unless:entity_type,individual','nullable','string','max:255'],
            'city'=>['nullable','string','max:255'],'force_duplicate'=>['nullable','boolean'],
        ])->validate();

        return $this->creation->withinOrganization($actor, function () use ($actor,$contact,$data,$entitlements) {
            $locked = WhatsAppInboundContact::where('organization_id',$actor->organization_id)->lockForUpdate()->findOrFail($contact->id);
            if ($locked->prospect_id) {
                $p = Prospect::where('organization_id',$actor->organization_id)->findOrFail($locked->prospect_id);
                $name = trim((string)($data[$data['entity_type'] === 'individual' ? 'individual_name' : 'company_name'] ?? ''));
                if ($p->entity_type !== $data['entity_type'] || $p->display_name !== $name) {
                    throw ValidationException::withMessages(['duplicate'=>'Kontak telah dikonversi ke identitas lain. Buka prospek yang sudah terhubung.']);
                }
                return $p;
            }
            $review = WhatsAppLeadReview::where('organization_id',$actor->organization_id)->where('whatsapp_inbound_contact_id',$locked->id)->first();
            $assigned = $review?->assigned_to;
            if ($assigned && !User::where('organization_id',$actor->organization_id)->where('role','sales')->where('is_active',true)->whereKey($assigned)->exists()) $assigned = null;
            $p = $this->creation->createWithin($actor, [
                ...$data, 'phone'=>$locked->phone_normalized,
                'contact_name'=>$data['entity_type'] === 'business' ? $locked->profile_name : null,
                'assigned_to'=>$assigned,'source_name'=>$locked->source_name ?: 'WhatsApp','source_url'=>$locked->source_url,
                'whatsapp_status'=>'valid','whatsapp_id'=>$locked->wa_id,'whatsapp_verified_at'=>now(),
                'last_customer_reply_at'=>$locked->last_message_at,'last_contact_at'=>$locked->last_message_at,
            ], $entitlements, [
                'activity_title'=>'Dikonversi dari lead WhatsApp',
                'activity_description'=>'Lead WhatsApp ditinjau dan dikonversi; tidak ada persetujuan marketing otomatis.',
            ]);
            $locked->update(['prospect_id'=>$p->id]);
            $this->attach($actor,$locked,$p);
            return $p;
        });
    }

    public function link(User $actor, WhatsAppInboundContact $contact, int $prospectId): Prospect
    {
        $this->assertTenant($actor,$contact);
        return $this->creation->withinOrganization($actor, function () use ($actor,$contact,$prospectId) {
            $locked = WhatsAppInboundContact::where('organization_id',$actor->organization_id)->lockForUpdate()->findOrFail($contact->id);
            if ($locked->prospect_id && (int)$locked->prospect_id !== $prospectId) {
                throw ValidationException::withMessages(['prospect_id'=>'Kontak sudah terhubung dengan prospek lain.']);
            }
            $p = Prospect::where('organization_id',$actor->organization_id)->findOrFail($prospectId);
            if ($p->phone_normalized !== $locked->phone_normalized) {
                throw ValidationException::withMessages(['prospect_id'=>'Nomor prospek tidak cocok dengan kontak WhatsApp.']);
            }
            $locked->update(['prospect_id'=>$p->id]);
            $this->attach($actor,$locked,$p);
            return $p;
        });
    }

    private function attach(User $actor, WhatsAppInboundContact $contact, Prospect $p): void
    {
        WhatsAppMessage::where('organization_id',$actor->organization_id)->where('direction','inbound')
            ->where('from_phone',$contact->phone_normalized)->whereNull('prospect_id')->update(['prospect_id'=>$p->id]);
        WhatsAppLeadReview::updateOrCreate(['whatsapp_inbound_contact_id'=>$contact->id], [
            'organization_id'=>$actor->organization_id,'status'=>'qualified','lead_kind'=>$p->entity_type,
            'reviewed_by'=>$actor->id,'reviewed_at'=>now(),
        ]);
    }

    private function assertTenant(User $actor, WhatsAppInboundContact $contact): void
    {
        abort_unless($actor->isAdmin() && $actor->is_active,403);
        abort_unless((int)$actor->organization_id === (int)$contact->organization_id,404);
    }
}
