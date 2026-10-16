<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\IsolatedDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        // Run before providers and before RefreshDatabase or a test's setUp can issue SQL.
        $app->afterBootstrapping(LoadConfiguration::class, static function (Application $app): void {
            IsolatedDatabaseGuard::protect($app);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
