<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses()->group('E3-F6-I1');

beforeEach(function (): void {
    config([
        'meilisearch.host' => 'http://meili.test:7700',
        'meilisearch.healthcheck.url' => 'http://meili.test:7700/health',
        'meilisearch.healthcheck.timeout' => 2,
        'meilisearch.headers' => static fn (): array => ['X-Meili-API-Key' => 'integration-key'],
        'meilisearch.correlation_prefix' => 'infra',
    ]);
});

it('E3-F6-I1: logs success when meilisearch is healthy', function (): void {
    Http::fake([
        'http://meili.test:7700/health' => Http::response(['status' => 'available'], 200),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toBe('meilisearch.health_check.ok');
            expect($context)->toHaveKeys(['correlation_id', 'duration_ms', 'host', 'http_status', 'status']);
            expect($context['status'])->toBe('available');

            return true;
        })
        ->andReturnNull();

    Log::shouldReceive('warning')->never();
    Log::shouldReceive('error')->never();

    $this->artisan('meilisearch:health-check')
        ->expectsOutput('Meilisearch health check passed.')
        ->assertExitCode(0);
});

it('E3-F6-I1: flags degraded health responses', function (): void {
    Http::fake([
        'http://meili.test:7700/health' => Http::response(['status' => 'maintenance'], 503),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toBe('meilisearch.health_check.degraded');
            expect($context['status'])->toBe('maintenance');
            expect($context['http_status'])->toBe(503);

            return true;
        })
        ->andReturnNull();

    Log::shouldReceive('info')->never();
    Log::shouldReceive('error')->never();

    $this->artisan('meilisearch:health-check')
        ->expectsOutput('Meilisearch health check reported a degraded status.')
        ->assertExitCode(1);
});

it('E3-F6-I1: reports failures when the host is unreachable', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('connection refused');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toBe('meilisearch.health_check.failed');
            expect($context)->toHaveKeys(['correlation_id', 'duration_ms', 'host', 'exception']);
            expect($context['exception']['message'])->toBe('connection refused');

            return true;
        })
        ->andReturnNull();

    Log::shouldReceive('info')->never();
    Log::shouldReceive('warning')->never();

    $this->artisan('meilisearch:health-check')
        ->expectsOutput('Meilisearch health check failed to connect.')
        ->assertExitCode(1);
});

it('E3-F6-I1: exposes backup configuration defaults', function (): void {
    $config = config('meilisearch.backup');

    expect($config['path'])->toEndWith('backups/meilisearch');
    expect($config['retention_days'])->toBe(14);
});
