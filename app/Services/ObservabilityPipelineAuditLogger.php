<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ObservabilityPipeline;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ObservabilityPipelineAuditLogger
{
    public function created(ObservabilityPipeline $pipeline, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($pipeline),
        ];

        $this->persist($pipeline, $actor, 'observability_pipeline.created', $payload);
        $this->logEvent('observability_pipeline.created', $pipeline, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(ObservabilityPipeline $pipeline, ?User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($pipeline, $actor, 'observability_pipeline.updated', $changes);
        $this->logEvent('observability_pipeline.updated', $pipeline, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(ObservabilityPipeline $pipeline, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($pipeline),
        ];

        $this->persist($pipeline, $actor, 'observability_pipeline.deleted', $payload);
        $this->logEvent('observability_pipeline.deleted', $pipeline, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(ObservabilityPipeline $pipeline): array
    {
        return [
            'name_digest' => hash('sha256', (string) $pipeline->name),
            'pipeline_type' => $pipeline->pipeline_type,
            'ingest_endpoint_digest' => $pipeline->ingestEndpointDigest(),
            'buffer_strategy' => $pipeline->buffer_strategy,
            'buffer_retention_seconds' => $pipeline->buffer_retention_seconds,
            'retry_backoff_seconds' => $pipeline->retry_backoff_seconds,
            'max_retry_attempts' => $pipeline->max_retry_attempts,
            'batch_max_bytes' => $pipeline->batch_max_bytes,
            'metrics_scrape_interval_seconds' => $pipeline->metrics_scrape_interval_seconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(ObservabilityPipeline $pipeline, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $pipeline->tenant_id,
            'brand_id' => $pipeline->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => ObservabilityPipeline::class,
            'auditable_id' => $pipeline->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, ObservabilityPipeline $pipeline, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'observability_pipeline_id' => $pipeline->getKey(),
            'tenant_id' => $pipeline->tenant_id,
            'brand_id' => $pipeline->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'observability_pipeline_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
