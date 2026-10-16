<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Organization;
use App\Models\Prospect;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WhatsAppInboundContact;
use App\Models\WhatsAppMessage;
use App\Services\ProspectCreationService;
use App\Services\ProspectImportService;
use App\Services\WhatsAppLeadConversionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CrmTestCase;

class ProspectCreationTest extends CrmTestCase
{
    private function actor(string $key, int $limit=2): User
    {
        $org=Organization::create(['name'=>'Tenant '.$key,'slug'=>'tenant-'.$key]);
        $plan=SubscriptionPlan::create(['key'=>'plan-'.$key,'name'=>'Plan '.$key,'user_limit'=>10,'prospect_limit'=>$limit,'features'=>['crm','whatsapp_api']]);
        Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'active','ends_at'=>now()->addMonth(),'activated_at'=>now()]);
        return User::create(['organization_id'=>$org->id,'name'=>'Admin '.$key,'email'=>$key.'@example.test','password'=>'test-password','role'=>'admin','is_active'=>true]);
    }

    private function contact(User $actor): WhatsAppInboundContact
    {
        return WhatsAppInboundContact::create(['organization_id'=>$actor->organization_id,'phone_normalized'=>'6281234567890','profile_name'=>'Profil','last_message_at'=>now(),'source_name'=>'Meta Ads']);
    }

    public function test_quota_is_checked_again_and_failure_leaves_no_partial_records(): void
    {
        $actor=$this->actor('quota',1);$service=app(ProspectCreationService::class);
        $service->create($actor,['company_name'=>'PT Pertama']);
        try {$service->create($actor,['company_name'=>'PT Kedua']);$this->fail('Quota must reject');}
        catch(ValidationException $e){$this->assertArrayHasKey('entitlement',$e->errors());}
        $this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
        $this->assertSame(1,DB::table('prospect_activities')->where('user_id',$actor->id)->count());
        $this->assertSame(0,DB::transactionLevel());
    }

    public function test_manual_duplicate_confirmation_keeps_two_distinct_individuals(): void
    {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->actor('duplicate');$service=app(ProspectCreationService::class);
        $data=['entity_type'=>'individual','individual_name'=>'Nama Individu','phone'=>'081234567890'];
        $first=$service->create($actor,$data);
        try {$service->create($actor,$data);$this->fail('Manual review is required');}
        catch(ValidationException $e){$this->assertArrayHasKey('duplicate',$e->errors());}
        $second=$service->create($actor,[...$data,'force_duplicate'=>true]);
        $this->assertNotSame($first->id,$second->id);
        $this->assertNull($first->company_key);
        $this->assertNull($second->whatsapp_opt_in_at);
    }

    public function test_conversion_replay_uses_the_same_identity_and_no_extra_quota(): void
    {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->actor('convert',1);$contact=$this->contact($actor);
        WhatsAppMessage::create(['organization_id'=>$actor->organization_id,'direction'=>'inbound','from_phone'=>$contact->phone_normalized,'provider_message_id'=>'wamid-native','body'=>'Pesan tersimpan']);
        $service=app(WhatsAppLeadConversionService::class);$data=['entity_type'=>'individual','individual_name'=>'Pelanggan Individu'];
        $first=$service->convert($actor,$contact,$data);
        config()->set('santovate.crm.native_individuals_enabled',false);
        $repeat=$service->convert($actor,$contact,$data);
        $this->assertSame($first->id,$repeat->id);
        $this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
        $this->assertNull($first->company_name);
        $this->assertNull($first->whatsapp_opt_in_at);
        $this->assertSame('Meta Ads',$first->source_name);
        $this->assertDatabaseHas('whatsapp_messages',['provider_message_id'=>'wamid-native','prospect_id'=>$first->id]);
        $this->assertDatabaseHas('whatsapp_lead_reviews',['whatsapp_inbound_contact_id'=>$contact->id,'lead_kind'=>'individual']);
    }

    public function test_conversion_quota_failure_retains_contact_and_messages(): void
    {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->actor('full',0);$contact=$this->contact($actor);
        WhatsAppMessage::create(['organization_id'=>$actor->organization_id,'direction'=>'inbound','from_phone'=>$contact->phone_normalized,'provider_message_id'=>'wamid-full','body'=>'Tetap ada']);
        try {app(WhatsAppLeadConversionService::class)->convert($actor,$contact,['entity_type'=>'individual','individual_name'=>'Individu']);$this->fail('Quota must reject');}
        catch(ValidationException $e){$this->assertArrayHasKey('entitlement',$e->errors());}
        $this->assertNull($contact->fresh()->prospect_id);
        $this->assertDatabaseHas('whatsapp_messages',['provider_message_id'=>'wamid-full','prospect_id'=>null]);
    }

    public function test_creation_rejects_a_preexisting_transaction_snapshot(): void
    {
        $actor=$this->actor('snapshot');DB::beginTransaction();
        try {app(ProspectCreationService::class)->create($actor,['company_name'=>'PT Ditolak']);$this->fail('Must own transaction');}
        catch(\LogicException $e){$this->assertStringContainsString('own its transaction',$e->getMessage());}
        finally {DB::rollBack();}
        $this->assertSame(0,Prospect::where('organization_id',$actor->organization_id)->count());
    }

    public function test_native_create_route_and_update_preserve_individual_contract(): void
    {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->actor('http');
        $data=['entity_type'=>'individual','individual_name'=>'Nama Awal','fit_score'=>0,'pain_score'=>0,'contact_score'=>0,'status'=>'baru','qualification_status'=>'new'];
        $this->actingAs($actor)->post('/prospects',$data)->assertSessionHasNoErrors()->assertRedirect();
        $p=Prospect::where('organization_id',$actor->organization_id)->firstOrFail();
        config()->set('santovate.crm.native_individuals_enabled',false);
        $this->put('/prospects/'.$p->id,[...$data,'individual_name'=>'Nama Baru'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Nama Baru',$p->fresh()->display_name);
        $this->assertNull($p->fresh()->company_key);
    }

    public function test_import_is_atomic_when_capacity_cannot_fit_the_batch(): void
    {
        $actor=$this->actor('import',1);$file=tempnam(sys_get_temp_dir(),'sv952-');
        file_put_contents($file,"Nama Perusahaan\nPT Pertama\nPT Kedua\n");
        $importer=app(ProspectImportService::class);
        try {
            $importer->withinOrganization($actor,function()use($importer,$actor,$file){
                $batch=ImportBatch::create(['organization_id'=>$actor->organization_id,'filename'=>'isolated.csv','imported_by'=>$actor->id,'assignment_mode'=>'unassigned']);
                return $importer->import($file,$actor,$batch,'skip',['mode'=>'unassigned']);
            });
            $this->fail('Batch must not fit.');
        } catch(\RuntimeException $e) {
            $this->assertStringContainsString('Kapasitas',$e->getMessage());
        } finally {unlink($file);}
        $this->assertSame(0,Prospect::where('organization_id',$actor->organization_id)->count());
        $this->assertSame(0,ImportBatch::where('organization_id',$actor->organization_id)->count());
    }

    public function test_another_tenant_cannot_convert_a_contact(): void
    {
        $owner=$this->actor('owner');$intruder=$this->actor('intruder');$contact=$this->contact($owner);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(WhatsAppLeadConversionService::class)->convert($intruder,$contact,['company_name'=>'PT Tidak Berhak']);
    }
}
