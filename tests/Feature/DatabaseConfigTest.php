<?php

function withEnvironment(array $variables, \Closure $callback): void
{
    $original = [];

    foreach ($variables as $key => $value) {
        $current = getenv($key);
        $original[$key] = $current === false ? null : $current;

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    try {
        $callback();
    } finally {
        foreach ($original as $key => $value) {
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

it('falls back to generic database credentials for the pgsql connection', function () {
    withEnvironment([
        'DB_PG_HOST' => null,
        'DB_PG_PORT' => null,
        'DB_PG_DATABASE' => null,
        'DB_PG_USERNAME' => null,
        'DB_PG_PASSWORD' => null,
        'DB_HOST' => '192.0.2.10',
        'DB_PORT' => '6543',
        'DB_DATABASE' => 'fallback_db',
        'DB_USERNAME' => 'fallback_user',
        'DB_PASSWORD' => 'fallback_secret',
    ], function () {
        $config = require base_path('config/database.php');

        $pgsql = $config['connections']['pgsql'];

        expect($pgsql['host'])->toBe('192.0.2.10')
            ->and($pgsql['port'])->toBe('6543')
            ->and($pgsql['database'])->toBe('fallback_db')
            ->and($pgsql['username'])->toBe('fallback_user')
            ->and($pgsql['password'])->toBe('fallback_secret');
    });
});

it('still prefers dedicated pgsql credentials when provided', function () {
    withEnvironment([
        'DB_HOST' => 'ignored-host',
        'DB_PORT' => '6543',
        'DB_DATABASE' => 'fallback_db',
        'DB_USERNAME' => 'fallback_user',
        'DB_PASSWORD' => 'fallback_secret',
        'DB_PG_HOST' => '203.0.113.5',
        'DB_PG_PORT' => '5544',
        'DB_PG_DATABASE' => 'override_db',
        'DB_PG_USERNAME' => 'override_user',
        'DB_PG_PASSWORD' => 'override_secret',
    ], function () {
        $config = require base_path('config/database.php');

        $pgsql = $config['connections']['pgsql'];

        expect($pgsql['host'])->toBe('203.0.113.5')
            ->and($pgsql['port'])->toBe('5544')
            ->and($pgsql['database'])->toBe('override_db')
            ->and($pgsql['username'])->toBe('override_user')
            ->and($pgsql['password'])->toBe('override_secret');
    });
});
