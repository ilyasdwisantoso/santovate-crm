<?php
namespace Tests\Unit;
use App\Services\ProspectScoringService;
use PHPUnit\Framework\TestCase;
class ProspectScoringServiceTest extends TestCase
{
    public function test_priority_thresholds_are_simple_and_predictable(): void
    {
        $service = new ProspectScoringService();
        $this->assertSame(['total'=>9,'priority'=>'tinggi'], $service->calculate(3,3,3));
        $this->assertSame(['total'=>5,'priority'=>'sedang'], $service->calculate(3,1,1));
        $this->assertSame(['total'=>2,'priority'=>'rendah'], $service->calculate(1,1,0));
    }
}
