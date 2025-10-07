<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CheckMeilisearchHealth extends Command
{
    protected $signature = 'meilisearch:health-check {--timeout=}';

    protected $description = 'Verify Meilisearch availability and emit structured health logs.';

    public function handle(): int
    {
        $host = config('meilisearch.host');
        $healthUrl = config('meilisearch.healthcheck.url');
        $timeout = (int) ($this->option('timeout') ?? config('meilisearch.healthcheck.timeout'));
        $timeout = max(1, $timeout);
        $headersResolver = config('meilisearch.headers');
        $headers = is_callable($headersResolver) ? $headersResolver() : [];

        $correlationId = sprintf('%s-%s', config('meilisearch.correlation_prefix'), Str::uuid());
        $startedAt = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(array_merge($headers, [
                    'X-Correlation-ID' => $correlationId,
                ]))
                ->acceptJson()
                ->get($healthUrl);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000, 0);
            $status = $response->json('status');

            if ($response->successful() && in_array($status, ['available', 'ready', 'ok'], true)) {
                Log::info('meilisearch.health_check.ok', [
                    'correlation_id' => $correlationId,
                    'duration_ms' => $durationMs,
                    'host' => $host,
                    'http_status' => $response->status(),
                    'status' => $status,
                ]);

                $this->info('Meilisearch health check passed.');

                return self::SUCCESS;
            }

            Log::warning('meilisearch.health_check.degraded', [
                'correlation_id' => $correlationId,
                'duration_ms' => $durationMs,
                'host' => $host,
                'http_status' => $response->status(),
                'status' => $status,
            ]);

            $this->error('Meilisearch health check reported a degraded status.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000, 0);

            Log::error('meilisearch.health_check.failed', [
                'correlation_id' => $correlationId,
                'duration_ms' => $durationMs,
                'host' => $host,
                'exception' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]);

            $this->error('Meilisearch health check failed to connect.');

            return self::FAILURE;
        }
    }
}
