<?php

namespace Tests\Unit;

use App\Services\IpaymuCallbackGuard;
use Tests\TestCase;

class IpaymuCallbackGuardTest extends TestCase
{
    private IpaymuCallbackGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new IpaymuCallbackGuard();
    }

    public function test_valid_signed_callback_business_fields_pass(): void
    {
        $this->assertNull($this->guard->paymentIssue(
            ['reference_id' => 'SVA-001', 'amount' => '150000', 'total' => '150000'],
            'SVA-001', 150000.0, 150000.0
        ));
    }

    public function test_missing_and_invalid_amount_must_not_activate(): void
    {
        $payload = ['reference_id' => 'SVA-001'];
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, null));
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, 0));
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, -1));
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, INF));
    }

    public function test_mismatched_amount_goes_to_manual_review(): void
    {
        $payload = ['reference_id' => 'SVA-001', 'amount' => '149999'];
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, 149999));
        $this->assertNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, 150000));
    }

    public function test_conflicting_references_are_rejected(): void
    {
        $payload = ['reference_id' => 'SVA-001', 'referenceId' => 'SVA-002'];
        $this->assertNotNull($this->guard->paymentIssue($payload, 'SVA-001', 150000, 150000));
        $this->assertNotNull($this->guard->paymentIssue(['referenceId'=>'SVA-002'], 'SVA-001', 150000, 150000));
        $this->assertSame('SVA-001', $this->guard->reference($payload));
    }

    public function test_tenant_organization_consistency(): void
    {
        $this->assertTrue($this->guard->organizationMatches(3, 3));
        $this->assertFalse($this->guard->organizationMatches(3, 4));
        $this->assertFalse($this->guard->organizationMatches(3, null));
    }

    public function test_payment_snapshot_cannot_activate_a_different_subscription_or_addon_price(): void
    {
        $this->assertTrue($this->guard->storedAmountsMatch(150000, 150000));
        $this->assertFalse($this->guard->storedAmountsMatch(150000, 300000));
        $this->assertFalse($this->guard->storedAmountsMatch(0, 150000));
    }

    public function test_paid_and_non_paid_statuses(): void
    {
        $this->assertTrue($this->guard->isPaid(['status'=>'berhasil', 'status_code'=>'1']));
        $this->assertTrue($this->guard->isPaid(['transaction_status_code'=>'6']));
        $this->assertFalse($this->guard->isPaid(['status'=>'pending', 'status_code'=>'0']));
        $this->assertSame('expired', $this->guard->failedStatus(['status'=>'expired']));
    }
}
