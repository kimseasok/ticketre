<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'admin/horizon'),

    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => [
        'web',
        'auth:'.env('HORIZON_GUARD', 'web'),
        'tenant',
        \App\Http\Middleware\EnsureTwoFactorEnrolled::class,
        \App\Http\Middleware\EnsureTenantAccess::class . ':infrastructure.horizon.view',
    ],

    'waits' => [
        'redis:default' => (int) env('HORIZON_WAIT_THRESHOLD', 60),
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => (bool) env('HORIZON_FAST_TERMINATION', false),

    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 256),

    'defaults' => [
        'supervisor-app' => [
            'connection' => env('HORIZON_QUEUE_CONNECTION', 'redis'),
            'queue' => explode(',', env('HORIZON_QUEUE', 'default')),
            'balance' => env('HORIZON_BALANCE', 'auto'),
            'minProcesses' => (int) env('HORIZON_MIN_PROCESSES', 1),
            'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 10),
            'maxTime' => (int) env('HORIZON_MAX_TIME', 0),
            'maxJobs' => (int) env('HORIZON_MAX_JOBS', 0),
            'memory' => (int) env('HORIZON_WORKER_MEMORY', 256),
            'tries' => (int) env('HORIZON_TRIES', 1),
            'timeout' => (int) env('HORIZON_TIMEOUT', 60),
            'nice' => (int) env('HORIZON_NICE', 0),
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-app' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 10),
                'balanceMaxShift' => (int) env('HORIZON_BALANCE_MAX_SHIFT', 1),
                'balanceCooldown' => (int) env('HORIZON_BALANCE_COOLDOWN', 3),
            ],
        ],

        'staging' => [
            'supervisor-app' => [
                'maxProcesses' => (int) env('HORIZON_STAGING_MAX_PROCESSES', 5),
            ],
        ],

        'local' => [
            'supervisor-app' => [
                'maxProcesses' => (int) env('HORIZON_LOCAL_MAX_PROCESSES', 3),
                'balance' => env('HORIZON_LOCAL_BALANCE', 'simple'),
            ],
        ],
    ],
];
