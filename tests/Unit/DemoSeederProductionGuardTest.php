<?php

namespace Tests\Unit;

use Database\Seeders\DemoAccountSeeder;
use Tests\TestCase;

class DemoSeederProductionGuardTest extends TestCase
{
    public function test_demo_seeder_rejects_production_before_any_database_write(): void
    {
        $original = config('app.env');
        try {
            config()->set('app.env', 'production');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('DemoAccountSeeder dilarang');
            (new DemoAccountSeeder())->run();
        } finally {
            config()->set('app.env', $original);
        }
    }
}
