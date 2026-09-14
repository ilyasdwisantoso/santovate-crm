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
        'va' => env('IPAYMU_VA'),
        'api_key' => env('IPAYMU_API_KEY'),
        'sandbox_url' => env('IPAYMU_SANDBOX_URL', 'https://sandbox.ipaymu.com'),
        'production_url' => env('IPAYMU_PRODUCTION_URL', 'https://my.ipaymu.com'),
    ],
    'whatsapp' => [
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],
];
