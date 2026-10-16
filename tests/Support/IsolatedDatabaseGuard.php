<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use RuntimeException;

final class IsolatedDatabaseGuard
{
    public static function protect(Application $app): void
    {
        $config = $app->make('config');
        $allowMysql = (string) Env::get('SANTOVATE_MYSQL_TESTS', '0') === '1';
        $connections = self::allowedConnections(
            (string) $config->get('app.env'),
            $app->configurationIsCached(),
            (string) $config->get('database.default'),
            (array) $config->get('database.connections'),
            $allowMysql,
        );

        // Do not leave the .env development connection available by an explicit name.
        $config->set('database.connections', $connections);

        // DatabaseManager dispatches this while PDO is still lazy. Also guard queries
        // after configuration changes, purges and dynamically built connections.
        $app->make('events')->listen(ConnectionEstablished::class, static function (ConnectionEstablished $event) use ($allowMysql): void {
            self::assertConnection($event->connection->getConfig(), $allowMysql);
            $event->connection->beforeExecuting(static function (string $sql, array $bindings, Connection $connection) use ($allowMysql): void {
                self::assertConnection($connection->getConfig(), $allowMysql);
            });
        });
    }

    public static function allowedConnections(
        string $environment,
        bool $configurationCached,
        string $default,
        array $connections,
        bool $allowMysql = false,
    ): array {
        if ($environment !== 'testing') self::deny('APP_ENV must be testing');
        if ($configurationCached) self::deny('cached application configuration is not allowed');
        if (!in_array($default, ['sqlite', 'mysql_testing'], true)) self::deny('unknown testing connection');
        if ($default === 'mysql_testing' && !$allowMysql) self::deny('MySQL testing requires explicit opt-in');

        $allowed = [];
        // The in-memory connection remains available to existing isolated fixtures.
        foreach (array_unique(['sqlite', $default]) as $name) {
            if (!isset($connections[$name]) || !is_array($connections[$name])) self::deny('testing connection is missing');
            self::assertConnection($connections[$name], $allowMysql);
            $allowed[$name] = $connections[$name];
        }

        return $allowed;
    }

    public static function assertConnection(array $connection, bool $allowMysql = false): void
    {
        if (!empty($connection['url']) || !empty($connection['read']) || !empty($connection['write'])) {
            self::deny('connection URLs and read/write overrides are not allowed');
        }

        if (($connection['driver'] ?? null) === 'sqlite') {
            if (($connection['database'] ?? null) !== ':memory:') self::deny('SQLite must use :memory:');
            return;
        }

        if (($connection['driver'] ?? null) !== 'mysql' || !$allowMysql) {
            self::deny('only in-memory SQLite or opted-in isolated MySQL is allowed');
        }

        if (!in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) self::deny('MySQL testing must use loopback');
        if (!empty($connection['unix_socket']) || !empty($connection['prefix'])) self::deny('MySQL sockets and table prefixes are not allowed');
        if (!preg_match('/\Asantovate_crm_test_[a-z0-9_]+\z/D', (string) ($connection['database'] ?? ''))) {
            self::deny('MySQL database must have the dedicated test prefix');
        }
        if (!preg_match('/\Asantovate_test(?:_[a-z0-9_]+)?\z/D', (string) ($connection['username'] ?? ''))) {
            self::deny('a dedicated test account is required; root and development accounts are forbidden');
        }

        $port = filter_var($connection['port'] ?? null, FILTER_VALIDATE_INT);
        if ($port === false || $port < 1 || $port > 65535) self::deny('invalid MySQL testing port');
    }

    private static function deny(string $reason): never
    {
        // Never print a DSN, credentials, connection URL or SQL payload here.
        throw new RuntimeException('Database safety guard: '.$reason.'. SQL execution is blocked.');
    }
}
