<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IsolatedConnectionTest extends TestCase
{
    public function test_connection_targets_the_explicit_disposable_schema(): void
    {
        $this->assertSame('mysql_testing', config('database.default'));
        $actual = DB::connection()->selectOne('SELECT DATABASE() AS database_name');
        $this->assertSame(config('database.connections.mysql_testing.database'), $actual->database_name);
        $this->assertMatchesRegularExpression('/\Asantovate_crm_test_[a-z0-9_]+\z/D', $actual->database_name);
    }
}
