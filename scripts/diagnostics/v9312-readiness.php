<?php
/**
 * Santovate CRM V9.3.12 – non-mutating configuration readiness overview.
 * Usage: php scripts/diagnostics/v9312-readiness.php [--strict]
 * Prints neither secret values nor personally identifying account information.
 * Does NOT prove real payment, WhatsApp connectivity, email delivery, or DB backups.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$strict = in_array('--strict', $argv, true);
$isProduction = app()->environment('production') || config('app.env') === 'production';
$sourceVersion = trim((string) @file_get_contents($root.'/VERSION'));
$checks = [
    ['Version file set to V9.3.12', $sourceVersion === '9.3.12', true],
    ['App key configured', filled(config('app.key')), true],
    ['Production APP_DEBUG=false', !$isProduction || !config('app.debug'), true],
    ['Production APP_URL uses HTTPS', !$isProduction || parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https', true],
    ['Production APP_ENV=production', !$isProduction || config('app.env') === 'production', true],
    ['Production email mailer is not log/array', !$isProduction || !in_array(config('mail.default'), ['log', 'array'], true), false],
    ['WhatsApp verify token configured', filled(config('santovate.whatsapp.verify_token')), false],
    ['WhatsApp app secret configured', filled(config('santovate.whatsapp.app_secret')), false],
    ['File logging uses rotation (daily or stack)', in_array(config('logging.default'), ['daily', 'stack'], true), false],
];

echo 'SANTOVATE CRM V9.3.12 – READ-ONLY READINESS CHECK'.PHP_EOL;
echo 'Environment category: '.($isProduction ? 'production' : 'non-production').PHP_EOL;
$failures = 0;
foreach ($checks as [$label, $passes, $isRequired]) {
    $state = $passes ? 'PASS' : ($isRequired ? 'FAIL' : 'REVIEW');
    if (!$passes && $isRequired) $failures++;
    echo '['.$state.'] '.$label.PHP_EOL;
}
echo 'Not checked: DB migration state, live gateway payments, actual SMTP delivery, Meta/WhatsApp onboarding, backup restorability.'.PHP_EOL;
echo 'This diagnostic does not change source files, databases, credentials, or production state.'.PHP_EOL;
if ($strict && $failures > 0) exit(2);
