<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsAppLeadReviewController;
use App\Models\Organization;
use App\Models\Prospect;
use App\Models\User;
use App\Models\WhatsAppInboundContact;
use App\Models\WhatsAppLeadReview;
use App\Models\WhatsAppMessage;
use App\Services\EntitlementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/** SQLite in-memory only. Never touches the CRM development or production database. */
class WhatsAppLeadReviewTest extends TestCase
{
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('Isolated SQLite required.');
        $this->previousDefault = config('database.default');
        config()->set('database.default','sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints',true);
        DB::purge('sqlite');
        Schema::create('organizations',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->string('status')->default('active');$t->json('settings')->nullable();$t->timestamps();});
        Schema::create('users',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('role')->default('sales');$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('prospects',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');$t->string('company_key')->unique();$t->string('company_name');$t->string('phone')->nullable();$t->string('phone_normalized')->nullable();$t->string('contact_name')->nullable();$t->string('city')->nullable();$t->string('source_name')->nullable();$t->text('source_url')->nullable();$t->string('whatsapp_status')->nullable();$t->string('whatsapp_id')->nullable();$t->timestamp('whatsapp_verified_at')->nullable();$t->timestamp('last_customer_reply_at')->nullable();$t->timestamp('last_contact_at')->nullable();$t->unsignedTinyInteger('fit_score')->default(0);$t->unsignedTinyInteger('pain_score')->default(0);$t->unsignedTinyInteger('contact_score')->default(0);$t->unsignedTinyInteger('total_score')->default(0);$t->string('priority')->default('rendah');$t->string('status')->default('baru');$t->unsignedBigInteger('assigned_to')->nullable();$t->unsignedBigInteger('created_by')->nullable();$t->timestamps();});
        Schema::create('whatsapp_inbound_contacts',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');$t->unsignedBigInteger('prospect_id')->nullable();$t->string('phone_normalized');$t->string('wa_id')->nullable();$t->string('profile_name')->nullable();$t->string('source_name')->nullable();$t->string('source_id')->nullable();$t->text('source_url')->nullable();$t->json('referral')->nullable();$t->timestamp('first_message_at')->nullable();$t->timestamp('last_message_at')->nullable();$t->string('last_message_id')->nullable();$t->timestamps();$t->unique(['organization_id','phone_normalized']);});
        Schema::create('whatsapp_messages',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');$t->unsignedBigInteger('prospect_id')->nullable();$t->string('provider_message_id')->unique();$t->string('direction');$t->string('from_phone')->nullable();$t->text('body')->nullable();$t->timestamps();});
        Schema::create('prospect_activities',function(Blueprint $t){$t->id();$t->unsignedBigInteger('prospect_id');$t->unsignedBigInteger('user_id')->nullable();$t->string('type');$t->string('title');$t->text('description')->nullable();$t->timestamp('occurred_at');$t->timestamps();});
        (require database_path('migrations/2026_10_12_160000_create_whatsapp_lead_reviews_table.php'))->up();
    }
    protected function tearDown(): void
    {
        if (isset($this->previousDefault)) {DB::disconnect('sqlite');config()->set('database.default',$this->previousDefault);DB::purge('sqlite');}
        parent::tearDown();
    }
    private function tenant(string $suffix): array
    {
        $org=Organization::create(['name'=>'Org '.$suffix,'slug'=>'org-'.$suffix]);
        $admin=User::create(['organization_id'=>$org->id,'name'=>'Admin '.$suffix,'email'=>'admin-'.$suffix.'@example.test','password'=>'abc123123','role'=>'admin','is_active'=>true]);
        $sales=User::create(['organization_id'=>$org->id,'name'=>'Sales '.$suffix,'email'=>'sales-'.$suffix.'@example.test','password'=>'abc123123','role'=>'sales','is_active'=>true]);
        return [$org,$admin,$sales];
    }
    private function contact(Organization $org, string $number): WhatsAppInboundContact
    {
        return WhatsAppInboundContact::create(['organization_id'=>$org->id,'phone_normalized'=>$number,'wa_id'=>$number,'profile_name'=>'Customer','last_message_at'=>now(),'last_message_id'=>'wamid-'.$number]);
    }
    private function request(User $user, array $data): Request
    {
        $r=Request::create('/whatsapp/leads','POST',$data);
        $r->setUserResolver(fn()=>$user);
        return $r;
    }
    private function mockEntitlement(bool $allow): EntitlementService
    {
        $mock=Mockery::mock(EntitlementService::class);
        if ($allow) $mock->shouldReceive('assertCanConsume')->once()->withAnyArgs()->andReturnNull();
        else $mock->shouldReceive('assertCanConsume')->once()->andThrow(ValidationException::withMessages(['entitlement'=>'Kuota penuh']));
        return $mock;
    }

    public function test_model_uses_actual_migration_table_name(): void
    {
        $this->assertSame('whatsapp_lead_reviews', (new WhatsAppLeadReview())->getTable());
        $this->assertTrue(Schema::hasTable((new WhatsAppLeadReview())->getTable()));
    }

    public function test_review_saves_individual_as_lead_without_fake_prospect(): void
    {
        [$org,$admin,$sales]=$this->tenant('first');$contact=$this->contact($org,'628111111111');
        $response=app(WhatsAppLeadReviewController::class)->review($this->request($admin,['status'=>'qualified','lead_kind'=>'individual','assigned_to'=>$sales->id,'notes'=>'Tanya produk']),$contact);
        $this->assertSame(302,$response->getStatusCode());
        $this->assertDatabaseHas('whatsapp_lead_reviews',['organization_id'=>$org->id,'status'=>'qualified','lead_kind'=>'individual','assigned_to'=>$sales->id]);
        $this->assertSame(0,Prospect::count());
    }
    public function test_cross_tenant_review_and_link_are_denied(): void
    {
        [$org,$admin]=$this->tenant('owner');[$other,$wrong]=$this->tenant('intruder');$contact=$this->contact($org,'628111111222');
        try {app(WhatsAppLeadReviewController::class)->review($this->request($wrong,['status'=>'qualified','lead_kind'=>'business']),$contact);$this->fail('Must fail');}
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(404,$e->getStatusCode());}
        $this->assertSame(0,WhatsAppLeadReview::count());
    }
    public function test_link_existing_prospect_checks_phone_and_tenant_and_backfills_messages(): void
    {
        [$org,$admin]=$this->tenant('link');$contact=$this->contact($org,'628111111333');
        $p=Prospect::create(['organization_id'=>$org->id,'company_name'=>'Perusahaan Nyata','phone'=>'08111111333']);
        WhatsAppMessage::create(['organization_id'=>$org->id,'direction'=>'inbound','from_phone'=>$contact->phone_normalized,'provider_message_id'=>'wamid-link','body'=>'Halo']);
        app(WhatsAppLeadReviewController::class)->link($this->request($admin,['prospect_id'=>$p->id]),$contact);
        $this->assertSame($p->id,$contact->fresh()->prospect_id);
        $this->assertDatabaseHas('whatsapp_messages',['provider_message_id'=>'wamid-link','prospect_id'=>$p->id]);
        $this->assertDatabaseHas('whatsapp_lead_reviews',['status'=>'qualified','lead_kind'=>'business']);
    }
    public function test_company_conversion_enforces_quota_before_any_prospect_write(): void
    {
        [$org,$admin]=$this->tenant('quota');$contact=$this->contact($org,'628111111444');
        try {app(WhatsAppLeadReviewController::class)->convert($this->request($admin,['company_name'=>'PT Satu','city'=>'Jakarta']),$contact,$this->mockEntitlement(false));$this->fail('Quota should block');}
        catch (ValidationException $e){$this->assertArrayHasKey('entitlement',$e->errors());}
        $this->assertNull($contact->fresh()->prospect_id);$this->assertSame(0,Prospect::count());
        $this->assertSame(1,WhatsAppInboundContact::count());
    }
    public function test_business_conversion_creates_real_company_without_fictitious_name(): void
    {
        [$org,$admin,$sales]=$this->tenant('biz');$contact=$this->contact($org,'628111111555');
        app(WhatsAppLeadReviewController::class)->review($this->request($admin,['status'=>'qualified','lead_kind'=>'business','assigned_to'=>$sales->id]),$contact);
        app(WhatsAppLeadReviewController::class)->convert($this->request($admin,['company_name'=>'PT Usaha Sebenarnya','city'=>'Depok']),$contact,$this->mockEntitlement(true));
        $p=Prospect::firstOrFail();
        $this->assertSame('PT Usaha Sebenarnya',$p->company_name);
        $this->assertSame($sales->id,$p->assigned_to);
        $this->assertSame($p->id,$contact->fresh()->prospect_id);
        $this->assertDatabaseHas('prospect_activities',['prospect_id'=>$p->id,'title'=>'Dikonversi dari lead WhatsApp']);
    }
    public function test_routes_enforce_admin_and_active_subscription(): void
    {
        $route=Route::getRoutes()->getByName('whatsapp.leads.index');
        $this->assertNotNull($route);
        $middleware=implode(' ', $route->gatherMiddleware());
        $this->assertStringContainsString('role:admin',$middleware);
        $this->assertStringContainsString('entitlement.feature:whatsapp_api',$middleware);
    }
}
