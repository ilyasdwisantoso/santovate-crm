<?php

declare(strict_types=1);

$directories = [
    'bootstrap/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'storage/app/imports/tmp',
];

foreach ($directories as $directory) {
    if (is_dir($directory)) {
        continue;
    }

    if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fwrite(
            STDERR,
            sprintf(
                "Failed to create Laravel runtime directory: %s%s",
                $directory,
                PHP_EOL
            )
        );

        exit(1);
    }
}

exit(0);
