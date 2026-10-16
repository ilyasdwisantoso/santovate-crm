<?php
namespace Tests\Feature;
use App\Models\{Prospect,Opportunity,Quotation,Deal,CommercialInvoice,PaymentTransaction,SalesCommission,WhatsAppInboundContact,WhatsAppChannel,WhatsAppMessage};
use App\Services\{ProspectCreationService,WhatsAppLeadConversionService,WhatsAppInboundCaptureService,ProspectStageService};
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\{CrmTestCase,NativeCrmFixtures};
class NativeCustomerFlowTest extends CrmTestCase {
    use NativeCrmFixtures;

    public function test_business_and_individual_share_the_commercial_flow_and_commission_rules(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->nativeActor('commerce');$this->actingAs($actor);
        foreach(['business','individual'] as $type){
            $name=$type==='business'?'PT Fixture Commerce':'Fixture Individual';
            $p=app(ProspectCreationService::class)->create($actor,['entity_type'=>$type,($type==='business'?'company_name':'individual_name')=>$name]);
            $this->post('/opportunities',['prospect_id'=>$p->id,'name'=>'Project '.$name,'status'=>'open','stage'=>'qualification','expected_value'=>1000000,'probability'=>50])->assertSessionHasNoErrors()->assertRedirect();
            $o=Opportunity::where('prospect_id',$p->id)->firstOrFail();
            $this->get('/opportunities/'.$o->id)->assertInertia(fn(Assert $page)=>$page->where('opportunity.prospect.display_name',$name)->where('opportunity.prospect.entity_type',$type));
            $this->post('/quotations',['prospect_id'=>$p->id,'opportunity_id'=>$o->id,'quotation_type'=>'project','pricing_type'=>'standard','discount_type'=>'percent','discount_value'=>0,'tax_percent'=>0,'items'=>[['name'=>'Fixture service','quantity'=>1,'unit'=>'project','unit_price'=>1000000,'discount_percent'=>0]]])->assertSessionHasNoErrors()->assertRedirect();
            $q=Quotation::where('prospect_id',$p->id)->firstOrFail();
            if($q->requires_approval)$this->post('/quotations/'.$q->id.'/approve')->assertRedirect();
            $this->post('/quotations/'.$q->id.'/send')->assertRedirect();
            $this->post('/quotations/'.$q->id.'/accept')->assertRedirect();
            $this->get('/quotations/'.$q->id.'/print')->assertInertia(fn(Assert $page)=>$page->where('quotation.prospect.display_name',$name)->where('quotation.prospect.company_name',$type==='business'?$name:null)->missing('quotation.items.0.internal_cost_snapshot'));
            $this->post('/deals/from-quotation/'.$q->id,['closing_date'=>now()->toDateString(),'actual_deal_value'=>1000000,'commissionable_value'=>800000,'contract_status'=>'not_required','schedules'=>[['label'=>'Full','percentage'=>100,'amount'=>1000000,'trigger_type'=>'deal']]])->assertSessionHasNoErrors()->assertRedirect();
            $d=Deal::where('prospect_id',$p->id)->firstOrFail();
            $this->post('/finance/invoices',['deal_id'=>$d->id,'payment_schedule_id'=>$d->paymentSchedules()->firstOrFail()->id,'issue_date'=>now()->toDateString(),'subtotal'=>1000000,'tax_amount'=>0,'status'=>'issued'])->assertSessionHasNoErrors()->assertRedirect();
            $invoice=CommercialInvoice::where('deal_id',$d->id)->firstOrFail();
            $payment=PaymentTransaction::create(['organization_id'=>$actor->organization_id,'commercial_invoice_id'=>$invoice->id,'deal_id'=>$d->id,'provider'=>'manual','status'=>'pending_verification','amount'=>1000000,'reference_id'=>'fixture-'.$type]);
            $this->post('/finance/payments/'.$payment->id.'/verify')->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('paid',$invoice->fresh()->status);
            $commission=SalesCommission::where('deal_id',$d->id)->firstOrFail();
            $this->assertSame(200000.0,(float)$commission->earned_amount);
            $this->get('/deals/'.$d->id)->assertInertia(fn(Assert $page)=>$page->where('deal.prospect.display_name',$name));
            $this->get('/quotations?q='.urlencode($name))->assertInertia(fn(Assert $page)=>$page->has('quotations.data',1)->where('quotations.data.0.prospect.id',$p->id));
            $this->get('/deals?q='.urlencode($name))->assertInertia(fn(Assert $page)=>$page->has('deals.data',1)->where('deals.data.0.prospect.id',$p->id));
            $this->assertSame($p->id,$d->prospect_id);
        }
        $this->get('/finance/commissions')->assertInertia(fn(Assert $page)=>$page->has('commissions.data',2));
        $this->get('/dashboard')->assertOk();
        $this->get('/pipeline')->assertOk();
        $this->get('/finance')->assertOk();
    }

    public function test_inbound_requires_manual_individual_link_then_retains_it_without_marketing_consent(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->nativeActor('inbound');
        $p=app(ProspectCreationService::class)->create($actor,['entity_type'=>'individual','individual_name'=>'Fixture Person','phone'=>'081234567890']);
        $channel=WhatsAppChannel::create(['organization_id'=>$actor->organization_id,'provider'=>'meta','phone_number_id'=>'fixture-native','is_active'=>true]);
        $capture=app(WhatsAppInboundCaptureService::class);$stages=app(ProspectStageService::class);
        $message=['id'=>'fixture-inbound-1','from'=>'6281234567890','type'=>'text','text'=>['body'=>'Fixture reply']];
        $capture->capture($channel,$message,[],$stages);
        $contact=WhatsAppInboundContact::where('organization_id',$actor->organization_id)->firstOrFail();
        $this->assertNull($contact->prospect_id);
        app(WhatsAppLeadConversionService::class)->link($actor,$contact,$p->id);
        $message['id']='fixture-inbound-2';
        $capture->capture($channel,$message,[],$stages);
        $capture->capture($channel,$message,[],$stages);
        $this->assertSame(2,WhatsAppMessage::where('prospect_id',$p->id)->count());
        $this->assertSame(1,DB::table('prospect_activities')->where('prospect_id',$p->id)->where('type','customer_reply')->count());
        $this->assertNull($p->fresh()->whatsapp_opt_in_at);
        $this->actingAs($actor)->post('/whatsapp/leads/'.$contact->id.'/review',['status'=>'qualified','lead_kind'=>'business'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('whatsapp_lead_reviews',['whatsapp_inbound_contact_id'=>$contact->id,'lead_kind'=>'individual']);
    }

    public function test_duplicate_candidates_never_reveal_another_tenant(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $owner=$this->nativeActor('candidate-owner');$viewer=$this->nativeActor('candidate-viewer');
        app(ProspectCreationService::class)->create($owner,['entity_type'=>'individual','individual_name'=>'Private Fixture','phone'=>'081234567890']);
        $this->actingAs($viewer)->getJson('/prospects/check-duplicates?entity_type=individual&phone=081234567890')->assertOk()->assertJsonCount(0,'duplicates');
    }
}
