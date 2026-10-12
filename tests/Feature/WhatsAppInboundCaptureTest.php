<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsAppWebhookController;
use App\Services\ProspectStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/** Dedicated in-memory SQLite schema; never migrates the CRM developer database. */
class WhatsAppInboundCaptureTest extends TestCase
{
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('SQLite PDO required for isolated webhook tests.');
        $this->previousDefault = config('database.default');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite');
        Schema::create('organizations', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->timestamps();
        });
        Schema::create('prospects', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id');
            $t->string('phone_normalized')->nullable(); $t->timestamps();
        });
        Schema::create('whatsapp_channels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id');
            $t->string('phone_number_id')->nullable(); $t->boolean('is_active')->default(false);
            $t->string('provider')->default('meta_cloud'); $t->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('prospect_id')->nullable(); $t->string('direction');
            $t->string('type'); $t->string('status'); $t->string('from_phone')->nullable();
            $t->string('provider_message_id')->nullable()->unique();
            $t->text('body')->nullable(); $t->json('provider_payload')->nullable(); $t->timestamps();
        });
        $migration = require database_path('migrations/2026_10_12_090000_create_whatsapp_inbound_contacts_table.php');
        $migration->up();
        config()->set('santovate.whatsapp.app_secret', 'isolated-test-secret');
    }

    protected function tearDown(): void
    {
        if (isset($this->previousDefault)) {
            DB::disconnect('sqlite');
            config()->set('database.default', $this->previousDefault);
            DB::purge('sqlite');
        }
        parent::tearDown();
    }

    private function addChannel(int $orgId, string $phoneId): void
    {
        DB::table('organizations')->insert([
            'id'=>$orgId, 'name'=>'Tenant '.$orgId, 'slug'=>'tenant-'.$orgId,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        DB::table('whatsapp_channels')->insert([
            'organization_id'=>$orgId, 'phone_number_id'=>$phoneId, 'is_active'=>true,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    private function webhook(string $phoneId, string $messageId, string $sender='6281234567890', ?array $referral=null): int
    {
        $message = ['id'=>$messageId, 'from'=>$sender, 'timestamp'=>(string)now()->timestamp,
            'type'=>'text', 'text'=>['body'=>'Halo, masih ada stok?']];
        if ($referral !== null) $message['referral'] = $referral;
        $body = json_encode(['entry'=>[['changes'=>[['value'=>[
            'metadata'=>['phone_number_id'=>$phoneId],
            'contacts'=>[['wa_id'=>$sender,'profile'=>['name'=>'Pembeli Baru']]],
            'messages'=>[$message],
        ]]]]]], JSON_THROW_ON_ERROR);
        $req = Request::create('/api/whatsapp/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE'=>'application/json',
            'HTTP_X_HUB_SIGNATURE_256'=>'sha256='.hash_hmac('sha256',$body,'isolated-test-secret'),
        ], $body);
        return app(WhatsAppWebhookController::class)->receive($req, app(ProspectStageService::class))->getStatusCode();
    }

    public function test_unknown_sender_is_saved_without_creating_fake_company(): void
    {
        $this->addChannel(1, 'phone-a');
        $this->assertSame(200, $this->webhook('phone-a','wamid-new-1','081234567890'));
        $this->assertDatabaseCount('whatsapp_inbound_contacts', 1);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseHas('whatsapp_inbound_contacts', [
            'organization_id'=>1, 'phone_normalized'=>'6281234567890', 'profile_name'=>'Pembeli Baru',
            'prospect_id'=>null,
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'organization_id'=>1,'provider_message_id'=>'wamid-new-1', 'direction'=>'inbound', 'prospect_id'=>null,
        ]);
    }

    public function test_repeated_webhook_keeps_a_single_contact_and_message(): void
    {
        $this->addChannel(1, 'phone-a');
        $this->assertSame(200, $this->webhook('phone-a','wamid-dup'));
        $this->assertSame(200, $this->webhook('phone-a','wamid-dup'));
        $this->assertDatabaseCount('whatsapp_inbound_contacts',1);
        $this->assertDatabaseCount('whatsapp_messages',1);
    }

    public function test_same_sender_is_scoped_to_the_correct_tenant(): void
    {
        $this->addChannel(1,'phone-a');
        $this->addChannel(2,'phone-b');
        $this->assertSame(200,$this->webhook('phone-a','wamid-org-a'));
        $this->assertSame(200,$this->webhook('phone-b','wamid-org-b'));
        $this->assertDatabaseCount('whatsapp_inbound_contacts',2);
        $this->assertDatabaseHas('whatsapp_messages',['organization_id'=>1,'provider_message_id'=>'wamid-org-a']);
        $this->assertDatabaseHas('whatsapp_messages',['organization_id'=>2,'provider_message_id'=>'wamid-org-b']);
    }

    public function test_ambiguous_channel_id_fails_closed_instead_of_routing_to_wrong_tenant(): void
    {
        $this->addChannel(1,'duplicate-phone-id');
        $this->addChannel(2,'duplicate-phone-id');
        $this->assertSame(503,$this->webhook('duplicate-phone-id','wamid-ambiguous'));
        $this->assertDatabaseCount('whatsapp_inbound_contacts',0);
        $this->assertDatabaseCount('whatsapp_messages',0);
    }

    public function test_meta_ad_referral_is_preserved_without_claiming_instagram_or_facebook_placement(): void
    {
        $this->addChannel(1,'phone-a');
        $this->webhook('phone-a','wamid-ref','6281234567890',[
            'source_type'=>'ad','source_id'=>'meta-ad-42',
            'source_url'=>'https://example.test/ad/42', 'headline'=>'Promo',
        ]);
        $this->assertDatabaseHas('whatsapp_inbound_contacts',[
            'organization_id'=>1, 'source_name'=>'Meta Ads', 'source_id'=>'meta-ad-42',
            'source_url'=>'https://example.test/ad/42',
        ]);
    }
}
