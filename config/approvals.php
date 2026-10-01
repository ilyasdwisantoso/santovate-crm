<?php

return [
    'quotation' => [
        'discount_percent_threshold' => (float) env('SANTOVATE_APPROVAL_DISCOUNT_PERCENT', 5),
        'high_value_threshold' => (int) env('SANTOVATE_APPROVAL_HIGH_VALUE', 50000000),
        'default_payment_terms' => env('SANTOVATE_DEFAULT_PAYMENT_TERMS', '50% DP, 30% UAT, 20% Go-Live'),
    ],
];
