<?php
namespace Tests\Feature;

use App\Mail\TeamInvitationMail;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamInvitationService;
use App\Services\TeamInvitationTokens;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Isolated SQLite test schema: never migrates or truncates the developer's real CRM database. */
class TeamInvitationFlowTest extends TestCase
{
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite is needed for isolated TeamInvitation database integration tests.');
        }
        $this->previousDefault = config('database.default');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite');

        Schema::create('organizations', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique();
            $t->unsignedBigInteger('business_configuration_id')->nullable();
            $t->string('status')->default('active'); $t->json('settings')->nullable(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id'); $t->string('name');
            $t->string('email')->unique(); $t->string('password');
            $t->string('role')->default('sales'); $t->boolean('is_active')->default(true);
            $t->boolean('is_platform_admin')->default(false); $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login_at')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->string('name');
            $t->unsignedInteger('user_limit'); $t->unsignedInteger('prospect_limit')->default(100);
            $t->json('features')->nullable(); $t->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id'); $t->unsignedBigInteger('subscription_plan_id');
            $t->unsignedBigInteger('business_configuration_id')->nullable();
            $t->string('status'); $t->string('billing_cycle')->default('monthly');
            $t->timestamp('ends_at')->nullable(); $t->timestamp('starts_at')->nullable();
            $t->json('entitlement_overrides')->nullable(); $t->timestamps();
        });
        Schema::create('prospects', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id'); $t->timestamps();
        });
        Schema::create('entitlement_grants', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('organization_id'); $t->unsignedBigInteger('subscription_id')->nullable();
            $t->string('status')->default('active'); $t->string('kind')->default('limit');
            $t->string('key'); $t->string('operation'); $t->integer('integer_value')->nullable();
            $t->boolean('boolean_value')->nullable(); $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable(); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('type');
            $t->morphs('notifiable'); $t->text('data');
            $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        // Load the actual deployed migration to validate FK and uniqueness rules.
        $migration = require database_path('migrations/2026_10_10_080000_create_team_invitations_table.php');
        $migration->up();
        Mail::fake();
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

    private function organization(string $slug, int $seatLimit = 2): array
    {
        $org = Organization::create(['name'=>ucwords($slug), 'slug'=>$slug, 'status'=>'active']);
        $plan = SubscriptionPlan::create(['key'=>$slug.'-plan','name'=>'Testing', 'user_limit'=>$seatLimit,'prospect_limit'=>100, 'features'=>[]]);
        Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'active','ends_at'=>now()->addYear()]);
        $admin = User::create([
            'organization_id'=>$org->id, 'name'=>'Admin '.$slug, 'email'=>'admin-'.$slug.'@example.test',
            'password'=>'Password12345', 'role'=>'admin', 'is_active'=>true, 'is_platform_admin'=>false,
        ]);
        return [$org, $admin];
    }

    private function service(): TeamInvitationService
    {
        return app(TeamInvitationService::class);
    }

    private function invitationPage(string $token): array
    {
        // Direct Inertia controller response: does not depend on a prebuilt Vite manifest or HTTP asset version.
        $page = app(\App\Http\Controllers\Auth\AcceptTeamInvitationController::class)->show($token, $this->service());
        $request = \Illuminate\Http\Request::create('/team-invite/'.$token, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = $page->toResponse($request);
        $this->assertSame(200, $response->getStatusCode());
        return json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_guest_sees_branded_unavailable_page_without_account_information(): void
    {
        $page = $this->invitationPage(str_repeat('a', 64));
        $this->assertSame('Auth/InvitationUnavailable', $page['component']);
        $this->assertArrayNotHasKey('invitation', $page['props']);
        $this->assertArrayNotHasKey('token', $page['props']);
    }

    public function test_pending_seat_reservation_and_acceptance_after_capacity_change(): void
    {
        [$org, $admin] = $this->organization('seat-a', 2);
        [$first, $token] = $this->service()->invite($admin,'sales-a@example.test','sales');
        $this->assertTrue($first->isRedeemable());
        try {
            $this->service()->invite($admin,'sales-b@example.test','sales');
            $this->fail('Second pending seat must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('entitlement', $e->errors());
        }
        // Simulates manual account creation consuming the last seat during a pending invite.
        User::create(['organization_id'=>$org->id, 'name'=>'Manual User', 'email'=>'manual@example.test',
            'password'=>'Password12345','role'=>'sales','is_active'=>true]);
        try {
            $this->service()->accept($token, 'Invited User','Password12345');
            $this->fail('Accept must reject when capacity is now full');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('entitlement', $e->errors());
        }
        $this->assertDatabaseMissing('users',['email'=>'sales-a@example.test']);
        $this->assertSame('pending', $first->fresh()->status);
    }

    public function test_revoke_prevents_reuse_and_resend(): void
    {
        [, $admin] = $this->organization('tenant-revoke', 2);
        [$invite, $token] = $this->service()->invite($admin,'revoked@example.test','sales');
        $this->service()->revoke($admin,$invite);
        $this->assertNull($this->service()->findRedeemable($token));
        $this->assertSame('revoked',$invite->fresh()->status);
        $this->expectException(ValidationException::class);
        $this->service()->resend($admin,$invite);
    }

    public function test_resend_rotates_token_and_expired_pending_can_be_refreshed(): void
    {
        [, $admin] = $this->organization('tenant-resend', 2);
        [$invite,$oldToken] = $this->service()->invite($admin,'rotation@example.test','sales');
        $invite->expires_at = now()->subMinute();
        $invite->updated_at = now()->subMinutes(2);
        $invite->save();
        [$updated,$newToken] = $this->service()->resend($admin,$invite);
        $this->assertNotSame($oldToken,$newToken);
        $this->assertNull($this->service()->findRedeemable($oldToken));
        $this->assertNotNull($this->service()->findRedeemable($newToken));
        $this->assertSame('pending',$updated->fresh()->status);
        $this->assertSame(TeamInvitationTokens::digest($newToken),$updated->fresh()->token_hash);
    }

    public function test_guest_accepts_once_and_second_visit_is_unavailable(): void
    {
        [$org,$admin] = $this->organization('tenant-accept', 2);
        [$invite,$token] = $this->service()->invite($admin,'once@example.test','sales');
        $user = $this->service()->accept($token,'Account Executive','Password12345');
        $this->assertSame((int)$org->id,(int)$user->organization_id);
        $this->assertSame('sales',$user->role);
        $this->assertSame('accepted',$invite->fresh()->status);
        $this->assertNull($this->service()->findRedeemable($token));
        $this->assertDatabaseCount('users',2);
        try {
            $this->service()->accept($token, 'Another Attempt', 'Password12345');
            $this->fail('Previously accepted token was reused');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('users',2);
        $page = $this->invitationPage($token);
        $this->assertSame('Auth/InvitationUnavailable',$page['component']);
    }

    public function test_other_workspace_admin_cannot_revoke_or_resend_an_invitation(): void
    {
        [, $adminA] = $this->organization('tenant-a', 2);
        [, $adminB] = $this->organization('tenant-b', 2);
        [$invite,$token] = $this->service()->invite($adminA,'target@example.test','sales');
        $this->actingAs($adminB)->delete('/admin/users/invitations/'.$invite->id)->assertNotFound();
        $this->actingAs($adminB)->post('/admin/users/invitations/'.$invite->id.'/resend')->assertNotFound();
        $this->assertSame('pending',$invite->fresh()->status);
        $this->assertSame(TeamInvitationTokens::digest($token),$invite->fresh()->token_hash);
    }

    public function test_admin_invite_sends_email_without_exposing_raw_token_in_database(): void
    {
        [, $admin] = $this->organization('tenant-mail', 2);
        $this->actingAs($admin)->post('/admin/users/invitations', [
            'email'=>'new-mail@example.test','role'=>'sales',
        ])->assertRedirect();
        Mail::assertSent(TeamInvitationMail::class, 1);
        $invite = TeamInvitation::query()->where('email','new-mail@example.test')->firstOrFail();
        $this->assertSame('pending', $invite->status);
        $this->assertSame(64, strlen($invite->token_hash));
        $this->assertSame('new-mail@example.test', $invite->email);
    }

    public function test_non_admin_cannot_send_invites(): void
    {
        [$org,$admin] = $this->organization('tenant-auth',2);
        $sales = User::create(['organization_id'=>$org->id,'name'=>'Sales','email'=>'sales-auth@example.test',
            'password'=>'Password12345','role'=>'sales','is_active'=>true]);
        $this->actingAs($sales)->post('/admin/users/invitations',[
            'email'=>'new@example.test','role'=>'sales',
        ])->assertForbidden();
        $this->assertDatabaseCount('team_invitations',0);
    }
}
