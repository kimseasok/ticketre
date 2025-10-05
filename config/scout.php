<?php

return [
    'driver' => env('SCOUT_DRIVER', 'null'),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => false,
    'prefix' => env('SCOUT_PREFIX', ''),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY', null),
        'index_settings' => [],
    ],
];
