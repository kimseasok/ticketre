<?php

return [
    'asset_disk' => env('BRAND_ASSET_DISK', 'public'),
    'asset_types' => [
        'primary_logo',
        'secondary_logo',
        'favicon',
        'portal_stylesheet',
    ],
    'assets' => [
        'cache_control' => env('BRAND_ASSET_CACHE_CONTROL', 'public, max-age=604800'),
    ],
    'defaults' => [
        'theme' => [
            'colors' => [
                'primary' => '#2563eb',
                'secondary' => '#0f172a',
                'accent' => '#38bdf8',
                'text' => '#0f172a',
            ],
            'settings' => [
                'button_radius' => 6,
                'font_family' => 'Inter',
            ],
        ],
        'assets' => [
            'primary_logo' => null,
            'secondary_logo' => null,
            'favicon' => null,
            'portal_stylesheet' => null,
        ],
    ],
    'verification' => [
        'expected_cname' => env('BRAND_EXPECTED_CNAME', 'brands.ticketre.test'),
        'allowed_suffixes' => array_filter(explode(',', env('BRAND_ALLOWED_DOMAIN_SUFFIXES', 'ticketre.test,ticketre.local'))),
        'ssl_authority' => env('BRAND_SSL_AUTHORITY', 'LetsEncrypt'),
        'max_attempts' => (int) env('BRAND_VERIFICATION_MAX_ATTEMPTS', 3),
        'retry_seconds' => (int) env('BRAND_VERIFICATION_RETRY', 60),
    ],
];
