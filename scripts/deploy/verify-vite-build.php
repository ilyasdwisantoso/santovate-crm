<?php

$buildDir = $argv[1] ?? 'public/build';
$buildDir = rtrim(str_replace('\\', '/', $buildDir), '/');
$manifestPath = $buildDir.'/manifest.json';

if (!is_file($manifestPath)) {
    fwrite(STDERR, "ERROR manifest tidak ditemukan: {$manifestPath}".PHP_EOL);
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "ERROR manifest JSON tidak valid: {$manifestPath}".PHP_EOL);
    exit(1);
}

$missing = [];
$checked = [];
$jsFound = false;

foreach ($manifest as $source => $entry) {
    if (!is_array($entry)) {
        continue;
    }

    if (!empty($entry['file'])) {
        $relative = ltrim((string) $entry['file'], '/');
        $path = $buildDir.'/'.$relative;
        $checked[$path] = is_file($path);
        if (str_ends_with(strtolower($relative), '.js')) {
            $jsFound = true;
        }
    }

    foreach (($entry['css'] ?? []) as $css) {
        $relative = ltrim((string) $css, '/');
        $path = $buildDir.'/'.$relative;
        $checked[$path] = is_file($path);
    }
}

foreach ($checked as $path => $ok) {
    echo ($ok ? 'OK   ' : 'MISS ').$path.PHP_EOL;
    if (!$ok) {
        $missing[] = $path;
    }
}

if (!$jsFound) {
    $fallbackJs = glob($buildDir.'/assets/*.js') ?: [];
    $jsFound = count($fallbackJs) > 0;
}

if (!$jsFound) {
    fwrite(STDERR, "ERROR tidak ada JavaScript bundle pada build Vite.".PHP_EOL);
    exit(1);
}

if ($missing) {
    fwrite(STDERR, 'ERROR missing assets: '.count($missing).PHP_EOL);
    exit(1);
}

echo 'VITE_BUILD_OK assets='.count($checked).PHP_EOL;
exit(0);
