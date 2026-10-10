<?php
/**
 * Santovate CRM V9.3.2 - read-only sandbox payment readiness diagnostic.
 * Never creates orders, calls the gateway, changes entitlements, or prints secrets.
 * Run from project root: php scripts/diagnostics/ipaymu-sandbox-preflight.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "BLOCKED: vendor/autoload.php tidak ada. Jalankan composer install.\n");
    exit(2);
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EntitlementGrant;
use App\Models\Organization;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use App\Services\IpaymuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$issues = [];
$warnings = [];
$ok = static function (string $label): void { echo '[PASS] '.$label.PHP_EOL; };
$blocked = static function (string $message) use (&$issues): void {
    $issues[] = $message;
    echo '[BLOCK] '.$message.PHP_EOL;
};
$warn = static function (string $message) use (&$warnings): void {
    $warnings[] = $message;
    echo '[WARN] '.$message.PHP_EOL;
};

$mode = (string)config('santovate.ipaymu.mode', '');
$environment = (string)config('app.env', 'unknown');
$appUrl = rtrim((string)config('app.url', ''), '/');
echo "============================================================\n";
echo " Santovate CRM V9.3.2 - SANDBOX PREFLIGHT (READ ONLY)\n";
echo "============================================================\n";
echo 'APP_ENV: '.$environment.' | IPAYMU_MODE: '.$mode.PHP_EOL;

if ($mode === 'sandbox') $ok('Mode iPaymu adalah sandbox');
else $blocked('HANYA sandbox yang boleh menggunakan preflight ini; mode sekarang: '.$mode);

$gateway = app(IpaymuService::class);
if ($mode === 'sandbox' && $gateway->configured()) $ok('Kredensial sandbox VA dan API key tersedia (nilai disembunyikan)');
else $blocked('Kredensial sandbox belum lengkap / mode tidak aman; tidak ada kredensial yang dicetak');

$secret = trim((string)config('santovate.ipaymu.sandbox.callback_secret', ''));
$va = trim((string)config('santovate.ipaymu.sandbox.va', ''));
if ($mode === 'sandbox' && ($secret !== '' || $va !== '')) $ok('Callback verification key sandbox tersedia (nilai disembunyikan)');
else $blocked('Callback verification key sandbox tidak tersedia');

foreach (['api.ipaymu.callback', 'api.ipaymu.addon-callback', 'subscription.addons.pay', 'subscription.addons.status', 'subscription.addons.payment-result'] as $routeName) {
    if (Route::has($routeName)) $ok('Route: '.$routeName);
    else $blocked('Route tidak ada: '.$routeName);
}

$host = strtolower((string)(parse_url($appUrl, PHP_URL_HOST) ?: ''));
$scheme = strtolower((string)(parse_url($appUrl, PHP_URL_SCHEME) ?: ''));
if ($scheme !== 'https' || $host === '' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
    $warn('APP_URL bukan HTTPS publik. Callback otomatis dari iPaymu tidak bisa diverifikasi end-to-end pada localhost tanpa staging/tunnel aman.');
} else {
    $ok('APP_URL menggunakan HTTPS; pastikan server memang dapat dijangkau oleh iPaymu');
}

echo "\nRoute callback yang perlu dikonfigurasi di merchant sandbox:\n";
echo '  '.($appUrl ?: '(APP_URL belum dikonfigurasi)').'/api/ipaymu/callback'.PHP_EOL;
echo '  '.($appUrl ?: '(APP_URL belum dikonfigurasi)').'/api/ipaymu/addon-callback'.PHP_EOL;

try {
    // SELECT-only queries. No seeding, migration, transactions, or updates.
    DB::connection()->getPdo();
    $ok('Database dapat dibaca');
    $orgs = Organization::query()->orderBy('id')->get();
    $eligible = 0;
    echo "\nWorkspaces yang dapat digunakan untuk pengujian UI sandbox:\n";
    foreach ($orgs as $org) {
        $internal = $org->slug === 'santovate-internal' || (bool)data_get($org->settings, 'internal', false);
        if ($internal) continue;
        $subscription = $org->activeSubscription();
        if (!$subscription) continue;
        $eligible++;
        $users = $org->users()->where('is_active', true)->count();
        echo '  - '.$org->slug.' | subscription: active | users: '.$users.PHP_EOL;
    }
    if ($eligible) $ok($eligible.' workspace non-internal memiliki subscription aktif');
    else $warn('Belum ada workspace non-internal dengan subscription aktif untuk uji add-on');

    $orders = SubscriptionAddonOrder::query()->count();
    $activated = SubscriptionAddonOrder::query()->where('status', 'activated')->count();
    $paid = SubscriptionAddonPayment::query()->where('status', 'paid')->count();
    $reviews = SubscriptionAddonPayment::query()->where('status', 'pending_verification')->count();
    $grants = EntitlementGrant::query()->active()->where('source_type', SubscriptionAddonOrder::class)->count();
    echo "\nOrder add-on: {$orders} | Activated: {$activated} | Payment paid: {$paid} | Review: {$reviews} | Active add-on grants: {$grants}\n";
    if ($orders === 0) $warn('Belum ada order sandbox. Test payment dan idempotency end-to-end BELUM terbukti.');
    if ($reviews > 0) $warn('Ada pembayaran pending_verification; harus ditinjau sebelum menganggap sandbox stabil.');
} catch (\Throwable $exception) {
    // Avoid printing exception details which can include host names or connection credentials.
    $blocked('Database atau skema belum siap dibaca. Periksa koneksi dan migration status secara lokal.');
}

$warn('Security follow-up: audit callback amount missing/mismatch, retry bersamaan, dan isolasi transaksi antar tenant sebelum production deploy.');
echo "\nSTATUS: ".($issues ? 'BLOCKED ('.count($issues).' masalah)' : 'PREFLIGHT BERHASIL (belum E2E)').PHP_EOL;
echo "Catatan: pemeriksaan bersifat read-only; tidak ada order / payment / grant yang dibuat.\n";
exit($issues ? 2 : 0);
