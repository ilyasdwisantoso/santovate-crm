<?php

namespace Tests\Unit;

use App\Support\UserDeletionEligibility;
use PHPUnit\Framework\TestCase;

class UserDeletionEligibilityTest extends TestCase
{
    public function test_active_account_without_references_can_be_deleted(): void
    {
        // Eligibility deliberately has no is_active input: both active and inactive
        // users can be deleted if no CRM/audit references exist.
        $this->assertTrue(UserDeletionEligibility::allows(false, false, false, 0));
    }

    public function test_referenced_account_cannot_be_deleted(): void
    {
        $this->assertFalse(UserDeletionEligibility::allows(false, false, false, 1));
        $this->assertFalse(UserDeletionEligibility::allows(false, false, false, 12));
    }

    public function test_self_platform_owner_and_last_admin_are_protected(): void
    {
        $this->assertFalse(UserDeletionEligibility::allows(true, false, false, 0));
        $this->assertFalse(UserDeletionEligibility::allows(false, true, false, 0));
        $this->assertFalse(UserDeletionEligibility::allows(false, false, true, 0));
    }
}
