<?php
namespace Tests\Integration\MySql;
use App\Models\{Prospect,WhatsAppInboundContact};
use App\Services\ProspectCreationService;
use Illuminate\Support\Facades\{DB,Schema};
use Symfony\Component\Process\{Process,InputStream};
use Tests\Support\NativeCrmFixtures;
use Tests\TestCase;
class NativeProspectTest extends TestCase {
    use NativeCrmFixtures;
    protected function setUp(): void {
        parent::setUp();
        config()->set('santovate.crm.native_individuals_enabled',true);
        if(!Schema::hasTable('prospects')){
            foreach(glob(database_path('migrations/*.php')) as $path){
                if(str_starts_with(basename($path),'2026_10_16_'))continue;
                (require $path)->up();
            }
        }
    }
    public function test_real_migration_preserves_all_legacy_values_and_foreign_keys(): void {
        $this->assertFalse(Schema::hasColumn('prospects','entity_type'));
        $actor=$this->nativeActor('legacy');
        // Historical B2B row before V9.5.2; no development data is copied.
        $p=Prospect::create(['organization_id'=>$actor->organization_id,'company_name'=>'PT Legacy Fixture','city'=>'Jakarta','created_by'=>$actor->id]);
        $before=(array)DB::table('prospects')->find($p->id);
        $foreignKeys=DB::select("SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='prospects' ORDER BY TABLE_NAME,CONSTRAINT_NAME");
        (require database_path('migrations/2026_10_16_090000_add_native_prospect_identity.php'))->up();
        (require database_path('migrations/2026_10_16_090100_add_prospect_identity_lookup_indexes.php'))->up();
        $after=(array)DB::table('prospects')->find($p->id);
        $this->assertSame('business',$after['entity_type']);$this->assertNull($after['individual_name']);
        unset($after['entity_type'],$after['individual_name']);$this->assertSame($before,$after);
        $this->assertCount(9,$foreignKeys);
        $this->assertEquals($foreignKeys,DB::select("SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='prospects' ORDER BY TABLE_NAME,CONSTRAINT_NAME"));
        $columns=collect(DB::select("SHOW COLUMNS FROM prospects"))->keyBy('Field');
        $this->assertSame('YES',$columns['company_name']->Null);$this->assertSame('YES',$columns['company_key']->Null);
        $individual=app(ProspectCreationService::class)->create($actor,['entity_type'=>'individual','individual_name'=>'MariaDB Fixture']);
        $this->assertNull($individual->company_key);
        $this->assertNull($individual->company_name);
        $indexes=collect(DB::select('SHOW INDEX FROM prospects'))->pluck('Key_name')->unique()->all();
        $this->assertContains('prospects_org_phone_index',$indexes);
        $this->assertContains('prospects_org_entity_updated_index',$indexes);
        try{(require database_path('migrations/2026_10_16_090000_add_native_prospect_identity.php'))->down();$this->fail('Rollback must refuse');}
        catch(\RuntimeException $e){$this->assertStringContainsString('forward-only',$e->getMessage());}
        $this->assertSame('MariaDB Fixture',$individual->fresh()->display_name);
        $this->assertSame($p->id,Prospect::findOrFail($p->id)->id);
    }
    public function test_last_quota_slot_serializes_form_import_and_conversion(): void {
        $actor=$this->nativeActor('race-quota',1);
        $contact=WhatsAppInboundContact::create(['organization_id'=>$actor->organization_id,'phone_normalized'=>'6281111000001']);
        $results=$this->race($actor->id,['form','import','convert'],$contact->id);
        $this->assertSame(1,collect($results)->where('result','created')->count());
        $this->assertSame(2,collect($results)->where('result','entitlement')->count());
        $this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
        $other=$this->nativeActor('race-other',1);
        $this->assertNotNull(app(ProspectCreationService::class)->create($other,['company_name'=>'PT Independent Tenant'])->id);
    }
    public function test_simultaneous_conversion_replays_return_one_identity(): void {
        $actor=$this->nativeActor('race-replay',1);
        $contact=WhatsAppInboundContact::create(['organization_id'=>$actor->organization_id,'phone_normalized'=>'6281111000002']);
        $results=$this->race($actor->id,['convert','convert'],$contact->id);
        $this->assertSame(['created','created'],array_column($results,'result'));
        $this->assertSame($results[0]['id'],$results[1]['id']);
        $this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
        $this->assertNull($contact->fresh()->prospect->whatsapp_opt_in_at);
    }
    public function test_duplicate_review_is_rechecked_after_the_organization_lock(): void {
        $actor=$this->nativeActor('race-duplicate',10);
        $results=$this->race($actor->id,['duplicate','duplicate']);
        $this->assertSame(1,collect($results)->where('result','created')->count());
        $this->assertSame(1,collect($results)->where('result','duplicate')->count());
        $this->assertSame(1,Prospect::where('organization_id',$actor->organization_id)->count());
    }
    public function test_native_identity_queries_and_selectors_on_real_mysql_driver(): void {
        $actor=$this->nativeActor('native-sql');
        $creation=app(ProspectCreationService::class);
        $person=$creation->create($actor,['entity_type'=>'individual','individual_name'=>'SQL Individual']);
        $business=$creation->create($actor,['company_name'=>'PT SQL Business']);
        $this->actingAs($actor);
        foreach([$person,$business] as $p)$this->post('/opportunities',['prospect_id'=>$p->id,'name'=>'Project '.$p->display_name,'status'=>'open','stage'=>'qualification','expected_value'=>1000000,'probability'=>50])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/opportunities?q=SQL%20Individual')->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->has('opportunities.data',1)->where('opportunities.data.0.prospect.company_name',null)->where('opportunities.data.0.prospect.display_name','SQL Individual'));
        $this->get('/prospects?entity_type=individual&q=SQL')->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->has('prospects.data',1)->where('prospects.data.0.id',$person->id));
        $this->get('/quotations/create?prospect_id='.$person->id)->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->has('prospects',2)->where('prospects.1.display_name','SQL Individual'));
    }
    private function race(int $actorId,array $modes,int $contactId=0): array {
        $cfg=config('database.connections.mysql_testing');
        $env=['APP_ENV'=>'testing','DB_CONNECTION'=>'mysql_testing','DB_DATABASE'=>':memory:','DB_URL'=>'','SANTOVATE_MYSQL_TESTS'=>'1','TEST_DB_HOST'=>$cfg['host'],'TEST_DB_PORT'=>(string)$cfg['port'],'TEST_DB_DATABASE'=>$cfg['database'],'TEST_DB_USERNAME'=>$cfg['username'],'TEST_DB_PASSWORD'=>$cfg['password'],'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','LOG_CHANNEL'=>'null','MAIL_MAILER'=>'log'];
        $children=[];$inputs=[];
        try{
            foreach($modes as $mode){
                $input=new InputStream();$child=new Process([PHP_BINARY,base_path('tests/Support/prospect-create-worker.php'),(string)$actorId,$mode,(string)$contactId],base_path(),$env,$input,30);
                $child->start();$children[]=$child;$inputs[]=$input;
            }
            $deadline=microtime(true)+15;
            do{
                $ready=true;
                foreach($children as $child){if(!str_contains($child->getOutput(),"READY\n"))$ready=false;if(!$child->isRunning()&&!$ready)throw new \RuntimeException('Worker failed before barrier: '.$child->getErrorOutput());}
                if(!$ready)usleep(20000);
            }while(!$ready&&microtime(true)<$deadline);
            $this->assertTrue($ready,'All workers must reach barrier');
            foreach($inputs as $input){$input->write("go\n");$input->close();}
            $results=[];
            foreach($children as $child){
                $child->wait();$this->assertSame(0,$child->getExitCode(),$child->getErrorOutput().$child->getOutput());
                $lines=explode("\n",trim($child->getOutput()));$results[]=json_decode(end($lines),true,512,JSON_THROW_ON_ERROR);
            }
            return $results;
        }finally{foreach($inputs as $input)$input->close();foreach($children as $child)if($child->isRunning())$child->stop(1);}
    }
}
