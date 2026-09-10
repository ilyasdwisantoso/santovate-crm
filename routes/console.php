<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('santovate:about', function () {
    $this->info('Santovate CRM is ready.');
})->purpose('Check Santovate CRM installation');
