<?php

namespace Tests\Unit;

use App\Services\QuotationService;
use PHPUnit\Framework\TestCase;

class QuotationServiceTest extends TestCase
{
    public function test_it_calculates_line_discount_global_discount_tax_and_total(): void
    {
        $service = new QuotationService();
        $totals = $service->totals([
            ['name'=>'CRM Implementation','quantity'=>1,'unit'=>'project','unit_price'=>30000000,'discount_percent'=>0],
            ['name'=>'Training','quantity'=>2,'unit'=>'session','unit_price'=>2500000,'discount_percent'=>10],
        ], 'percent', 5, 11);

        $this->assertSame(34500000.0, $totals['subtotal']);
        $this->assertSame(1725000.0, $totals['discount_amount']);
        $this->assertSame(3605250.0, $totals['tax_amount']);
        $this->assertSame(36380250.0, $totals['grand_total']);
    }

    public function test_approval_is_required_for_large_discount_custom_pricing_or_large_total(): void
    {
        $service = new QuotationService();
        $base = ['discount_type'=>'percent','discount_value'=>6,'pricing_type'=>'standard'];
        $this->assertTrue($service->approvalRequired($base, ['subtotal'=>10000000,'discount_amount'=>600000,'grand_total'=>9400000]));

        $custom = ['discount_type'=>'percent','discount_value'=>0,'pricing_type'=>'custom'];
        $this->assertTrue($service->approvalRequired($custom, ['subtotal'=>10000000,'discount_amount'=>0,'grand_total'=>10000000]));

        $large = ['discount_type'=>'percent','discount_value'=>0,'pricing_type'=>'standard'];
        $this->assertTrue($service->approvalRequired($large, ['subtotal'=>50000000,'discount_amount'=>0,'grand_total'=>50000000]));
    }
}
