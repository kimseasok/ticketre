<?php

namespace App\Services;

use App\Models\ObservabilityPipeline;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ObservabilityPipelineService
{
    public function __construct(
        private readonly ObservabilityPipelineAuditLogger $auditLogger,
        private readonly ObservabilityMetricRecorder $metricRecorder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): ObservabilityPipeline
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var ObservabilityPipeline $pipeline */
        $pipeline = DB::transaction(fn () => ObservabilityPipeline::create($attributes));
        $pipeline->refresh();

        $this->auditLogger->created($pipeline, $actor, $startedAt, $correlation);
        $this->recordMetrics($pipeline, 'create', $startedAt);
        $this->logPerformance('observability_pipeline.create', $pipeline, $actor, $startedAt, $correlation);

        return $pipeline;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ObservabilityPipeline $pipeline, array $data, User $actor, ?string $correlationId = null): ObservabilityPipeline
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $pipeline);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($pipeline->getOriginal(), [
            'name',
            'slug',
            'pipeline_type',
            'ingest_endpoint',
            'ingest_protocol',
            'buffer_strategy',
            'buffer_retention_seconds',
            'retry_backoff_seconds',
            'max_retry_attempts',
            'batch_max_bytes',
            'metrics_scrape_interval_seconds',
            'brand_id',
            'metadata',
        ]);

        $dirty = [];

        DB::transaction(function () use ($pipeline, $attributes, &$dirty): void {
            $pipeline->fill($attributes);
            $dirty = Arr::except($pipeline->getDirty(), ['updated_at']);
            $pipeline->save();
        });

        $pipeline->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            if ($field === 'ingest_endpoint') {
                $changes['ingest_endpoint_digest'] = [
                    'old' => isset($original['ingest_endpoint']) ? hash('sha256', (string) $original['ingest_endpoint']) : null,
                    'new' => $pipeline->ingestEndpointDigest(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $pipeline->{$field},
            ];
        }

        $this->auditLogger->updated($pipeline, $actor, $changes, $startedAt, $correlation);
        $this->recordMetrics($pipeline, 'update', $startedAt);
        $this->logPerformance('observability_pipeline.update', $pipeline, $actor, $startedAt, $correlation);

        return $pipeline;
    }

    public function delete(ObservabilityPipeline $pipeline, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $pipeline->delete());

        $this->auditLogger->deleted($pipeline, $actor, $startedAt, $correlation);
        $this->recordMetrics($pipeline, 'delete', $startedAt);
        $this->logPerformance('observability_pipeline.delete', $pipeline, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?ObservabilityPipeline $pipeline = null): array
    {
        $defaults = config('observability.defaults');

        $attributes = Arr::only($data, [
            'name',
            'slug',
            'pipeline_type',
            'ingest_endpoint',
            'ingest_protocol',
            'buffer_strategy',
            'buffer_retention_seconds',
            'retry_backoff_seconds',
            'max_retry_attempts',
            'batch_max_bytes',
            'metrics_scrape_interval_seconds',
            'brand_id',
            'metadata',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $pipeline?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $pipeline?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        $attributes['pipeline_type'] = strtolower((string) ($attributes['pipeline_type'] ?? $pipeline?->pipeline_type ?? 'logs'));

        if (! array_key_exists('name', $attributes) && ! $pipeline) {
            $attributes['name'] = 'Observability Pipeline '.Str::random(6);
        }

        if (! array_key_exists('slug', $attributes) || empty($attributes['slug'])) {
            $source = $attributes['name'] ?? $pipeline?->name ?? 'observability-pipeline';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        if (isset($attributes['ingest_endpoint'])) {
            $attributes['ingest_endpoint'] = $this->sanitizeEndpoint($attributes['ingest_endpoint']);
        } elseif (! $pipeline) {
            $attributes['ingest_endpoint'] = 'https://logs.example.com/ingest';
        }

        if (! isset($attributes['ingest_protocol']) || $attributes['ingest_protocol'] === null) {
            $attributes['ingest_protocol'] = $this->resolveProtocol($attributes['ingest_endpoint'] ?? $pipeline?->ingest_endpoint);
        }

        if (isset($attributes['buffer_strategy'])) {
            $attributes['buffer_strategy'] = strtolower((string) $attributes['buffer_strategy']);
        } elseif (! $pipeline) {
            $attributes['buffer_strategy'] = 'disk';
        }

        $attributes['buffer_retention_seconds'] = $this->resolveInteger(
            $attributes['buffer_retention_seconds'] ?? $pipeline?->buffer_retention_seconds ?? $defaults['buffer_retention_seconds']
        );
        $attributes['retry_backoff_seconds'] = $this->resolveInteger(
            $attributes['retry_backoff_seconds'] ?? $pipeline?->retry_backoff_seconds ?? $defaults['retry_backoff_seconds']
        );
        $attributes['max_retry_attempts'] = $this->resolveInteger(
            $attributes['max_retry_attempts'] ?? $pipeline?->max_retry_attempts ?? $defaults['max_retry_attempts']
        );
        $attributes['batch_max_bytes'] = $this->resolveInteger(
            $attributes['batch_max_bytes'] ?? $pipeline?->batch_max_bytes ?? $defaults['batch_max_bytes']
        );

        if ($attributes['pipeline_type'] === 'metrics') {
            $attributes['metrics_scrape_interval_seconds'] = $this->resolveInteger(
                $attributes['metrics_scrape_interval_seconds']
                    ?? $pipeline?->metrics_scrape_interval_seconds
                    ?? $defaults['metrics_scrape_interval_seconds']
            );
        } else {
            $attributes['metrics_scrape_interval_seconds'] = null;
        }

        if (! isset($attributes['metadata'])) {
            $attributes['metadata'] = $pipeline?->metadata ?? [];
        } elseif (! is_array($attributes['metadata'])) {
            $attributes['metadata'] = (array) $attributes['metadata'];
        }

        return $attributes;
    }

    protected function sanitizeEndpoint(?string $endpoint): ?string
    {
        if ($endpoint === null) {
            return null;
        }

        $trimmed = trim($endpoint);
        if ($trimmed === '') {
            return null;
        }

        return preg_replace('/\s+/', '', $trimmed) ?? $trimmed;
    }

    protected function resolveProtocol(?string $endpoint): ?string
    {
        if (! $endpoint) {
            return null;
        }

        $parsed = parse_url($endpoint);

        return isset($parsed['scheme']) ? strtolower($parsed['scheme']) : null;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function resolveInteger(int|float|string|null $value): int
    {
        return (int) max(0, (int) $value);
    }

    protected function recordMetrics(ObservabilityPipeline $pipeline, string $operation, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $operationLabels = [
            'tenant_id' => (string) $pipeline->tenant_id,
            'brand_id' => $pipeline->brand_id ? (string) $pipeline->brand_id : 'unscoped',
            'pipeline_type' => $pipeline->pipeline_type,
            'operation' => $operation,
        ];

        $gaugeLabels = Arr::except($operationLabels, ['operation']);

        $this->metricRecorder->incrementCounter('observability_pipeline_operations_total', $operationLabels);
        $this->metricRecorder->observeSummary('observability_pipeline_operation_duration_ms', $operationLabels, $durationMs);
        $this->metricRecorder->setGauge('observability_pipeline_buffer_seconds', $gaugeLabels, (float) $pipeline->buffer_retention_seconds);
        $this->metricRecorder->setGauge('observability_pipeline_retry_attempts', $gaugeLabels, (float) $pipeline->max_retry_attempts);
    }

    protected function logPerformance(string $action, ObservabilityPipeline $pipeline, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'observability_pipeline_id' => $pipeline->getKey(),
            'tenant_id' => $pipeline->tenant_id,
            'brand_id' => $pipeline->brand_id,
            'pipeline_type' => $pipeline->pipeline_type,
            'buffer_strategy' => $pipeline->buffer_strategy,
            'buffer_retention_seconds' => $pipeline->buffer_retention_seconds,
            'retry_backoff_seconds' => $pipeline->retry_backoff_seconds,
            'max_retry_attempts' => $pipeline->max_retry_attempts,
            'batch_max_bytes' => $pipeline->batch_max_bytes,
            'metrics_scrape_interval_seconds' => $pipeline->metrics_scrape_interval_seconds,
            'ingest_endpoint_digest' => $pipeline->ingestEndpointDigest(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'observability_pipeline_service',
        ]);
    }
}
