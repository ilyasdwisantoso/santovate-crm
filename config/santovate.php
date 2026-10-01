<?php

return [
    'support' => [
        'email' => env('SANTOVATE_SUPPORT_EMAIL'),
        'phone' => env('SANTOVATE_SUPPORT_PHONE', '081293047587'),
        'whatsapp' => env('SANTOVATE_WHATSAPP', '6281293047587'),
        'address' => env('SANTOVATE_BUSINESS_ADDRESS'),
    ],
    'ipaymu' => [
        'mode' => env('IPAYMU_MODE', 'sandbox'),

        // Keep sandbox and production credentials side by side. When both are
        // configured, switching environment only requires changing IPAYMU_MODE.
        // IPAYMU_VA / IPAYMU_API_KEY remain as backward-compatible fallbacks.
        'sandbox' => [
            'va' => env('IPAYMU_SANDBOX_VA', env('IPAYMU_VA')),
            'api_key' => env('IPAYMU_SANDBOX_API_KEY', env('IPAYMU_API_KEY')),
            'url' => env('IPAYMU_SANDBOX_URL', 'https://sandbox.ipaymu.com'),
        ],
        'production' => [
            'va' => env('IPAYMU_PRODUCTION_VA', env('IPAYMU_VA')),
            'api_key' => env('IPAYMU_PRODUCTION_API_KEY', env('IPAYMU_API_KEY')),
            'url' => env('IPAYMU_PRODUCTION_URL', 'https://my.ipaymu.com'),
        ],
    ],
    'whatsapp' => [
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],
];
