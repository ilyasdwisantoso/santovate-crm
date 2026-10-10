<?php

namespace Tests\Unit;

use App\Mail\TeamInvitationMail;
use App\Models\TeamInvitation;
use App\Services\TeamInvitationTokens;
use Tests\TestCase;

class TeamInvitationTokenTest extends TestCase
{
    public function test_token_is_random_sha256_only_and_not_reusable_from_digest(): void
    {
        $a = TeamInvitationTokens::generate();
        $b = TeamInvitationTokens::generate();
        $this->assertNotSame($a, $b);
        $this->assertTrue(TeamInvitationTokens::valid($a));
        $this->assertSame(64, strlen(TeamInvitationTokens::digest($a)));
        $this->assertNotSame($a, TeamInvitationTokens::digest($a));
        $this->assertFalse(TeamInvitationTokens::valid('not-a-real-token'));
    }

    public function test_invitation_expiry_and_one_time_status_are_enforced(): void
    {
        $invite = new TeamInvitation(['status' => 'pending', 'expires_at' => now()->addHour()]);
        $this->assertTrue($invite->isRedeemable());
        $invite->status = 'accepted';
        $this->assertFalse($invite->isRedeemable());
        $invite->status = 'pending';
        $invite->expires_at = now()->subMinute();
        $this->assertFalse($invite->isRedeemable());
    }

    public function test_team_invitation_routes_have_role_auth_and_throttles(): void
    {
        $invite = app('router')->getRoutes()->getByName('admin.team-invitations.store');
        $accept = app('router')->getRoutes()->getByName('team-invitation.accept');
        $this->assertNotNull($invite);
        $this->assertNotNull($accept);
        $this->assertContains('role:admin', $invite->gatherMiddleware());
        $this->assertContains('account.active', $invite->gatherMiddleware());
        $this->assertContains('throttle:10,1', $accept->gatherMiddleware());
        $this->assertNotContains('auth', $accept->gatherMiddleware());
    }

    public function test_mail_contains_invitation_subject_and_plain_text_view(): void
    {
        $mail = new TeamInvitationMail('Demo Org', 'Administrator', 'https://example.test/team-invite/token', '12-10-2026');
        $this->assertStringContainsString('Demo Org', $mail->envelope()->subject);
        $this->assertSame('emails.team-invitation', $mail->content()->text);
    }
}
