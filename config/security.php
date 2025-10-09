<?php

return [
    'two_factor' => [
        'session_ttl_minutes' => env('TWO_FACTOR_SESSION_TTL', 30),
        'max_attempts' => env('TWO_FACTOR_MAX_ATTEMPTS', 5),
        'lockout_minutes' => env('TWO_FACTOR_LOCKOUT_MINUTES', 5),
        'recovery_codes' => env('TWO_FACTOR_RECOVERY_CODES', 10),
    ],
];
