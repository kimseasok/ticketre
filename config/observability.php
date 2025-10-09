<?php

return [
    'metrics_cache_store' => env('OBSERVABILITY_METRICS_STORE', 'array'),

    'defaults' => [
        'buffer_retention_seconds' => 900,
        'retry_backoff_seconds' => 30,
        'max_retry_attempts' => 5,
        'batch_max_bytes' => 1048576,
        'metrics_scrape_interval_seconds' => 30,
    ],
];
