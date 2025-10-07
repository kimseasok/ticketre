<?php

return [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key' => env('MEILISEARCH_KEY'),
    'healthcheck' => [
        'url' => env('MEILISEARCH_HEALTHCHECK_URL', env('MEILISEARCH_HOST', 'http://localhost:7700') . '/health'),
        'timeout' => (int) env('MEILISEARCH_HEALTHCHECK_TIMEOUT', 2),
    ],
    'backup' => [
        'path' => env('MEILISEARCH_BACKUP_PATH', storage_path('app/backups/meilisearch')),
        'retention_days' => (int) env('MEILISEARCH_BACKUP_RETENTION_DAYS', 14),
    ],
    'indexes' => [
        'kb_articles' => [
            'name' => env('MEILISEARCH_KB_ARTICLES_INDEX', 'kb_articles'),
        ],
    ],
    'headers' => static fn (): array => array_filter([
        'X-Meili-API-Key' => env('MEILISEARCH_KEY'),
    ]),
    'correlation_prefix' => env('MEILISEARCH_CORRELATION_PREFIX', 'meilisearch'),
];
