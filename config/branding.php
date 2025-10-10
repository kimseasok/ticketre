<?php

return [
    'asset_disk' => env('BRAND_ASSET_DISK', 'public'),
    'verification' => [
        'expected_cname' => env('BRAND_EXPECTED_CNAME', 'brands.ticketre.test'),
        'allowed_suffixes' => array_filter(explode(',', env('BRAND_ALLOWED_DOMAIN_SUFFIXES', 'ticketre.test,ticketre.local'))),
        'ssl_authority' => env('BRAND_SSL_AUTHORITY', 'LetsEncrypt'),
        'max_attempts' => (int) env('BRAND_VERIFICATION_MAX_ATTEMPTS', 3),
        'retry_seconds' => (int) env('BRAND_VERIFICATION_RETRY', 60),
    ],
];
