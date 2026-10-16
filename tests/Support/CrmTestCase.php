<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/** Real migrations with only legacy MySQL-specific subscription ALTERs adapted for SQLite. */
abstract class CrmTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('CrmTestCase requires isolated SQLite memory.');
        }
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (basename($path) === '2026_09_15_023804_alter_subscription_date_columns_to_datetime.php') continue;
            if (basename($path) === '2026_09_15_001000_add_business_theme_and_repair_subscription_dates.php') {
                Schema::table('business_configurations', function (Blueprint $table) { $table->json('theme')->nullable(); });
                continue;
            }
            (require $path)->up();
        }
    }
}
