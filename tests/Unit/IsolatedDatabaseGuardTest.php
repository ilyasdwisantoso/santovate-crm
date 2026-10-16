<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\IsolatedDatabaseGuard;

class IsolatedDatabaseGuardTest extends TestCase
{
    private static function sqlite(): array
    {
        return ['driver'=>'sqlite', 'database'=>':memory:', 'url'=>null];
    }

    private static function mysql(): array
    {
        return [
            'driver'=>'mysql', 'host'=>'127.0.0.1', 'port'=>3307,
            'database'=>'santovate_crm_test_v952_probe', 'username'=>'santovate_test_v952',
            'password'=>'test-placeholder', 'url'=>null, 'unix_socket'=>'', 'prefix'=>'',
        ];
    }

    public function test_development_connection_is_removed_instead_of_left_available(): void
    {
        $connections = IsolatedDatabaseGuard::allowedConnections('testing', false, 'sqlite', [
            'sqlite'=>self::sqlite(),
            'mysql'=>['driver'=>'mysql', 'database'=>'crm_santovate', 'username'=>'root'],
        ]);

        $this->assertSame(['sqlite'], array_keys($connections));
    }

    public function test_explicit_mysql_mode_keeps_only_test_mysql_and_memory_sqlite(): void
    {
        $connections = IsolatedDatabaseGuard::allowedConnections('testing', false, 'mysql_testing', [
            'sqlite'=>self::sqlite(), 'mysql_testing'=>self::mysql(),
            'mysql'=>['driver'=>'mysql', 'database'=>'crm_santovate'],
        ], true);

        $this->assertSame(['sqlite', 'mysql_testing'], array_keys($connections));
    }

    #[DataProvider('unsafeBootConfigurations')]
    public function test_unsafe_boot_configuration_fails_before_sql(
        string $environment, bool $cached, string $default, bool $allowMysql,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database safety guard:');
        IsolatedDatabaseGuard::allowedConnections($environment, $cached, $default, [
            'sqlite'=>self::sqlite(), 'mysql_testing'=>self::mysql(),
        ], $allowMysql);
    }

    public static function unsafeBootConfigurations(): array
    {
        return [
            'production'=>['production', false, 'sqlite', false],
            'local'=>['local', false, 'sqlite', false],
            'cached configuration'=>['testing', true, 'sqlite', false],
            'development connection'=>['testing', false, 'mysql', true],
            'mysql without opt-in'=>['testing', false, 'mysql_testing', false],
        ];
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_connection_is_rejected_without_connecting(array $connection, bool $allowMysql): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SQL execution is blocked');
        IsolatedDatabaseGuard::assertConnection($connection, $allowMysql);
    }

    public static function unsafeConnections(): array
    {
        $mysql = self::mysql();
        return [
            'SQLite file'=>[['driver'=>'sqlite', 'database'=>'database/live.sqlite'], false],
            'SQLite URL'=>[[...self::sqlite(), 'url'=>'sqlite:///database/live.sqlite'], false],
            'SQLite URL through MySQL opt-in'=>[[...self::sqlite(), 'url'=>'sqlite:///database/live.sqlite'], true],
            'active development DB'=>[[...$mysql, 'database'=>'crm_santovate'], true],
            'other application DB'=>[[...$mysql, 'database'=>'db_ekspedisi'], true],
            'empty DB'=>[[...$mysql, 'database'=>''], true],
            'unbounded prefix'=>[[...$mysql, 'database'=>'santovate_crm_test_'], true],
            'MySQL without opt-in'=>[$mysql, false],
            'root account'=>[[...$mysql, 'username'=>'root'], true],
            'remote server'=>[[...$mysql, 'host'=>'production.example.test'], true],
            'MySQL URL'=>[[...$mysql, 'url'=>'mysql://hidden@localhost/crm_santovate'], true],
            'read override'=>[[...$mysql, 'read'=>['database'=>'crm_santovate']], true],
            'write override'=>[[...$mysql, 'write'=>['database'=>'crm_santovate']], true],
            'socket override'=>[[...$mysql, 'unix_socket'=>'/mysql.sock'], true],
            'table prefix'=>[[...$mysql, 'prefix'=>'crm_santovate.'], true],
            'invalid port'=>[[...$mysql, 'port'=>0], true],
            'unknown driver'=>[['driver'=>'pgsql', 'database'=>'santovate_crm_test_fake'], true],
        ];
    }
}
