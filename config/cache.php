<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_DRIVER', 'redis-fallback'),

    'stores' => [
        'apc' => [
            'driver' => 'apc',
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('CACHE_REDIS_CONNECTION', 'cache'),
        ],
        'redis-fallback' => [
            'driver' => 'redis_fallback',
            'connection' => env('CACHE_REDIS_CONNECTION', 'cache'),
            'fallback' => env('CACHE_FALLBACK_STORE', 'file'),
            'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'ServiceDesk'), '_').'_cache'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'ServiceDesk'), '_').'_cache'),
];
