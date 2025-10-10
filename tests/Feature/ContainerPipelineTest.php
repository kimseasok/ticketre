<?php

test('Dockerfile defines dependency, tester, and runtime stages for E11-F1-I2', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)
        ->toContain('AS dependencies')
        ->toContain('AS tester')
        ->toContain('AS runtime')
        ->toContain('pdo_sqlite')
        ->toContain('php artisan test --no-interaction --without-tty');
})->group('E11-F1-I2');

test('CI workflow builds and exercises container pipeline for E11-F1-I2', function () {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

    expect($workflow)
        ->toContain('container:')
        ->toContain('--target dependencies')
        ->toContain('--target tester')
        ->toContain('--target runtime')
        ->toContain('ticketre-runtime-${{ github.sha }}');
})->group('E11-F1-I2');
