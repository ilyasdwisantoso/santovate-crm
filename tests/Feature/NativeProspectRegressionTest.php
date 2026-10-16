<?php
namespace Tests\Feature;
use App\Models\{Prospect,ImportBatch,User};
use App\Services\{ProspectCreationService,ProspectImportService};
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\{CrmTestCase,NativeCrmFixtures};
class NativeProspectRegressionTest extends CrmTestCase {
    use NativeCrmFixtures;
    private function importCsv(User $actor,string $body,string $mode='skip'): array {
        $file=tempnam(sys_get_temp_dir(),'sv952-import-');file_put_contents($file,$body);
        $importer=app(ProspectImportService::class);
        try{return $importer->withinOrganization($actor,function()use($importer,$actor,$file,$mode){
            $batch=ImportBatch::create(['organization_id'=>$actor->organization_id,'filename'=>'fixture.csv','imported_by'=>$actor->id,'assignment_mode'=>'unassigned']);
            return $importer->import($file,$actor,$batch,$mode,['mode'=>'unassigned']);
        });}finally{unlink($file);}
    }
    public function test_same_file_business_duplicates_use_one_quota_slot_and_existing_updates_use_none(): void {
        $actor=$this->nativeActor('import-existing',1);
        $result=$this->importCsv($actor,"Nama Perusahaan,Kota,Catatan\nPT Fixture,Jakarta,Pertama\nPT Fixture,Jakarta,Kedua\n");
        $this->assertSame(1,$result['imported']);$this->assertSame(1,$result['skipped']);
        $p=Prospect::where('organization_id',$actor->organization_id)->firstOrFail();$originalId=$p->id;
        $result=$this->importCsv($actor,"Nama Perusahaan,Kota,Catatan\nPT Fixture,Jakarta,Diperbarui\n",'update');
        $this->assertSame(1,$result['updated']);$this->assertSame('Diperbarui',$p->fresh()->notes);
        $this->assertSame($originalId,$p->fresh()->id);$this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
    }
    public function test_ambiguous_business_keys_and_individual_phone_matches_require_manual_review(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->nativeActor('import-review');$creation=app(ProspectCreationService::class);
        $first=$creation->create($actor,['company_name'=>'PT Ambiguous']);
        $second=$creation->create($actor,['company_name'=>'PT Ambiguous','force_duplicate'=>true]);
        $person=$creation->create($actor,['entity_type'=>'individual','individual_name'=>'Phone Owner','phone'=>'081234567890']);
        $result=$this->importCsv($actor,"Nama Perusahaan,Telepon WhatsApp,Catatan\nPT Ambiguous,,Jangan ubah\nPT Another,081234567890,Jangan gabung\n",'update');
        $this->assertCount(2,$result['errors']);$this->assertSame(0,$result['imported']);$this->assertSame(0,$result['updated']);
        $this->assertNull($first->fresh()->notes);$this->assertNull($second->fresh()->notes);$this->assertNull($person->fresh()->company_name);
    }
    public function test_hidden_ae_candidate_has_no_identifying_details_and_creation_requires_review(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $admin=$this->nativeActor('ae-review');
        $sales=[];
        foreach(['one','two'] as $key)$sales[]=User::create(['organization_id'=>$admin->organization_id,'name'=>'Sales '.$key,'email'=>'ae-'.$key.'@example.test','password'=>'test-password','role'=>'sales','is_active'=>true]);
        app(ProspectCreationService::class)->create($sales[0],['entity_type'=>'individual','individual_name'=>'Private AE Customer','phone'=>'081234567890']);
        $this->actingAs($sales[1])->getJson('/prospects/check-duplicates?entity_type=individual&phone=081234567890')->assertOk()->assertJsonCount(0,'duplicates');
        try{app(ProspectCreationService::class)->create($sales[1],['entity_type'=>'individual','individual_name'=>'Different Customer','phone'=>'081234567890']);$this->fail('Review required');}
        catch(ValidationException $e){
            $this->assertArrayHasKey('duplicate',$e->errors());
            $this->assertStringNotContainsString('Private AE Customer',json_encode($e->errors()));
        }
    }
    public function test_flag_off_keeps_individual_search_filter_detail_and_edit_available(): void {
        config()->set('santovate.crm.native_individuals_enabled',true);
        $actor=$this->nativeActor('flag-read');$creation=app(ProspectCreationService::class);
        $person=$creation->create($actor,['entity_type'=>'individual','individual_name'=>'Visible Individual']);
        $creation->create($actor,['company_name'=>'PT Visible Business']);
        config()->set('santovate.crm.native_individuals_enabled',false);
        $this->actingAs($actor)->get('/prospects?q=Visible&entity_type=individual')->assertInertia(fn(Assert $page)=>$page->has('prospects.data',1)->where('prospects.data.0.id',$person->id)->where('prospects.data.0.display_name','Visible Individual')->where('crm.native_individuals_enabled',false));
        $this->get('/prospects/'.$person->id)->assertInertia(fn(Assert $page)=>$page->where('prospect.company_name',null)->where('prospect.display_name','Visible Individual'));
        $this->get('/prospects/'.$person->id.'/edit')->assertOk();
    }
    public function test_normalized_business_key_variants_require_manual_duplicate_confirmation(): void {
        $actor=$this->nativeActor('company-key');$creation=app(ProspectCreationService::class);
        $creation->create($actor,['company_name'=>'PT. Fixture','city'=>'Jakarta']);
        $this->expectException(ValidationException::class);
        $creation->create($actor,['company_name'=>'PT Fixture','city'=>'Jakarta']);
    }
    public function test_legacy_schema_accepts_business_creation_and_rejects_new_individuals_even_when_flag_is_on(): void {
        // SQLite memory only: reconstruct the pre-upgrade column shape.
        \Illuminate\Support\Facades\Schema::table('prospects',function($table){$table->dropIndex('prospects_org_entity_updated_index');$table->dropColumn(['entity_type','individual_name']);});
        $actor=$this->nativeActor('legacy-compat');config()->set('santovate.crm.native_individuals_enabled',true);
        $p=app(ProspectCreationService::class)->create($actor,['company_name'=>'PT Pre Upgrade']);
        $this->assertSame('business',$p->entity_type);$this->assertSame('PT Pre Upgrade',$p->display_name);
        $this->expectException(ValidationException::class);
        app(ProspectCreationService::class)->create($actor,['entity_type'=>'individual','individual_name'=>'Blocked Before Schema']);
    }
}
