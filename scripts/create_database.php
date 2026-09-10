<?php

declare(strict_types=1);

$host = getenv('SANTOVATE_DB_HOST') ?: '127.0.0.1';
$port = getenv('SANTOVATE_DB_PORT') ?: '3306';
$database = getenv('SANTOVATE_DB_NAME') ?: '';
$username = getenv('SANTOVATE_DB_USER') ?: 'root';
$password = getenv('SANTOVATE_DB_PASS');
$password = $password === false ? '' : $password;

if ($database === '' || !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nama database tidak valid.\n");
    exit(3);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) $port);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '``', $database)
    ));
    fwrite(STDOUT, "Database '{$database}' siap.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membuat/mengakses database: {$e->getMessage()}\n");
    exit(1);
}
