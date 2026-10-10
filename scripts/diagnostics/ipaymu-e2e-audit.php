<?php
/** V9.3.9 read-only local audit. No gateway call, secrets, tokens, payment mutation or migration. */
declare(strict_types=1);
$root = dirname(__DIR__, 2);
chdir($root);
if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "BLOCKED: composer dependencies absent.\n"); exit(2);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use Illuminate\Support\Facades\DB;

$mode = (string)config('santovate.ipaymu.mode', '');
echo "Santovate V9.3.9 | READ-ONLY iPaymu payment evidence\n";
echo "Gateway mode: ".$mode."\n";
if ($mode !== 'sandbox') {
    fwrite(STDERR, "BLOCKED: audit sandbox hanya diizinkan saat IPAYMU_MODE=sandbox.\n"); exit(2);
}
try {
    DB::connection()->getPdo();
    foreach ([
        'Subscription payments' => Payment::query(),
        'Add-on payments' => SubscriptionAddonPayment::query(),
    ] as $label => $query) {
        $base = clone $query;
        $counts = [];
        foreach (['pending', 'paid', 'pending_verification', 'failed', 'expired', 'cancelled'] as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }
        echo $label.': '.json_encode($counts, JSON_UNESCAPED_SLASHES)."\n";
    }
    $activated = SubscriptionAddonOrder::query()->where('status', 'activated')->count();
    $unverified = Payment::query()->where('status', 'pending_verification')->count()
        + SubscriptionAddonPayment::query()->where('status', 'pending_verification')->count();
    echo 'Activated add-on orders: '.$activated."\n";
    echo 'Manual review records: '.$unverified."\n";
    echo 'Evidence only: row status is NOT proof of a verified provider payment or successful live callback.' . "\n";
    if ($unverified) echo "WARN: manual review required before considering payment flow stable.\n";
    echo "READ_ONLY_AUDIT_OK\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "BLOCKED: database/schema unavailable; details withheld.\n"); exit(2);
}
