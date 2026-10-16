<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_default_database_is_memory_sqlite_and_schema_writes_stay_there(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('isolation_probe', function (Blueprint $table) {
            $table->id();
            $table->string('value');
        });
        DB::table('isolation_probe')->insert(['value'=>'memory-only']);
        $this->assertSame('memory-only', DB::table('isolation_probe')->value('value'));
    }

    public function test_explicit_development_connection_is_unavailable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DB::connection('mysql');
    }

    public function test_changed_sqlite_file_configuration_is_rejected(): void
    {
        DB::purge('sqlite');
        config()->set('database.connections.sqlite.database', 'database/live.sqlite');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SQLite must use :memory:');
        DB::connection('sqlite')->select('SELECT 1');
    }

    public function test_dynamic_development_mysql_connection_is_rejected_before_pdo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database safety guard:');
        DB::build([
            'driver'=>'mysql', 'host'=>'127.0.0.1', 'port'=>3307,
            'database'=>'crm_santovate', 'username'=>'root', 'password'=>'not-used',
        ])->select('SELECT 1');
    }
}
