<?php

return [
    'default' => env('BROADCAST_DRIVER', env('ECHO_ENABLED', false) ? 'pusher' : 'log'),

    'connections' => [
        'log' => [
            'driver' => 'log',
        ],
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST'),
                'port' => env('PUSHER_PORT'),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'useTLS' => (bool) env('PUSHER_FORCE_TLS', env('PUSHER_SCHEME', 'https') === 'https'),
                'encrypted' => (bool) env('PUSHER_ENCRYPTED', env('PUSHER_SCHEME', 'https') === 'https'),
            ],
            'client_options' => [
                'timeout' => (int) env('PUSHER_CLIENT_TIMEOUT', 5),
            ],
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
