<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'redis-fallback'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', env('CACHE_REDIS_CONNECTION', 'cache')),
    'table' => 'sessions',
    'store' => env('SESSION_STORE', 'redis-fallback'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'ServiceDesk'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', null),
    'secure' => env('SESSION_SECURE_COOKIE', null),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
];
