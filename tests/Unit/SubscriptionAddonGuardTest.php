<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Services\SubscriptionAddonService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionAddonGuardTest extends TestCase
{
    public function test_paid_addons_are_blocked_for_internal_workspace(): void
    {
        $org = new Organization(['slug' => 'santovate-internal']);
        $this->expectException(ValidationException::class);
        (new SubscriptionAddonService())->assertPurchasable($org);
    }

    public function test_paid_addons_are_permitted_for_normal_workspace(): void
    {
        $org = new Organization(['slug' => 'acme-test']);
        (new SubscriptionAddonService())->assertPurchasable($org);
        $this->assertTrue(true);
    }
}
