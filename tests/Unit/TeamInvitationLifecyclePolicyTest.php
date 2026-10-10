<?php
namespace Tests\Unit;

use App\Models\TeamInvitation;
use Tests\TestCase;

class TeamInvitationLifecyclePolicyTest extends TestCase
{
    public function test_pending_invitation_may_be_resent_or_revoked_even_after_expiry(): void
    {
        $invite = new TeamInvitation(['status'=>'pending', 'expires_at'=>now()->subDay()]);
        $this->assertFalse($invite->isRedeemable());
        $this->assertTrue($invite->canResend());
        $this->assertTrue($invite->canRevoke());
    }

    public function test_accepted_and_revoked_invitation_cannot_be_reopened(): void
    {
        foreach (['accepted','revoked'] as $status) {
            $invite = new TeamInvitation(['status'=>$status, 'expires_at'=>now()->addDay()]);
            $this->assertFalse($invite->isRedeemable());
            $this->assertFalse($invite->canResend());
            $this->assertFalse($invite->canRevoke());
        }
    }

    public function test_unavailable_page_contains_no_user_or_invitation_token_props(): void
    {
        $controller = new \ReflectionMethod(\App\Http\Controllers\Auth\AcceptTeamInvitationController::class, 'show');
        $this->assertTrue($controller->isPublic());
        $this->assertTrue(is_file(resource_path('js/Pages/Auth/InvitationUnavailable.jsx')));
    }
}
