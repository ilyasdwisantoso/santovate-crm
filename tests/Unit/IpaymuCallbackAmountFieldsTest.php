<?php

namespace Tests\Unit;

use App\Services\IpaymuService;
use PHPUnit\Framework\TestCase;

class IpaymuCallbackAmountFieldsTest extends TestCase
{
    public function test_documented_amount_field_takes_priority(): void
    {
        $this->assertSame(150000.0, (new IpaymuService())->callbackAmount([
            'amount' => '150000', 'total' => '150000', 'sub_total' => '150000', 'paid_off' => 148500,
        ]));
    }

    public function test_total_fallback_is_supported(): void
    {
        $this->assertSame(150000.0, (new IpaymuService())->callbackAmount(['total' => '150000']));
    }

    public function test_subtotal_fallback_is_supported(): void
    {
        $this->assertSame(150000.0, (new IpaymuService())->callbackAmount(['sub_total' => '150000']));
    }

    public function test_paid_off_is_not_confused_with_gross_amount(): void
    {
        $this->assertNull((new IpaymuService())->callbackAmount(['paid_off' => 148500]));
    }

    public function test_missing_or_invalid_amount_fails_to_extract(): void
    {
        $gateway = new IpaymuService();
        $this->assertNull($gateway->callbackAmount([]));
        $this->assertNull($gateway->callbackAmount(['amount' => 'not-a-number']));
    }
}
