<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ObservabilityStack;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ObservabilityStackAuditLogger
{
    public function created(ObservabilityStack $stack, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($stack),
        ];

        $this->persist($stack, $actor, 'observability_stack.created', $payload);
        $this->logEvent('observability_stack.created', $stack, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(ObservabilityStack $stack, ?User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($stack, $actor, 'observability_stack.updated', $changes);
        $this->logEvent('observability_stack.updated', $stack, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(ObservabilityStack $stack, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($stack),
        ];

        $this->persist($stack, $actor, 'observability_stack.deleted', $payload);
        $this->logEvent('observability_stack.deleted', $stack, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(ObservabilityStack $stack): array
    {
        return [
            'name_digest' => $stack->nameDigest(),
            'status' => $stack->status,
            'logs_tool_digest' => $stack->logsToolDigest(),
            'metrics_tool_digest' => $stack->metricsToolDigest(),
            'alerts_tool' => $stack->alerts_tool,
            'log_retention_days' => $stack->log_retention_days,
            'metric_retention_days' => $stack->metric_retention_days,
            'trace_retention_days' => $stack->trace_retention_days,
            'estimated_monthly_cost' => $stack->estimated_monthly_cost !== null
                ? (float) $stack->estimated_monthly_cost
                : null,
            'decision_matrix_options' => collect($stack->decision_matrix ?? [])
                ->pluck('option')
                ->map(fn ($option) => hash('sha256', (string) $option))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(ObservabilityStack $stack, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $stack->tenant_id,
            'brand_id' => $stack->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => ObservabilityStack::class,
            'auditable_id' => $stack->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, ObservabilityStack $stack, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'observability_stack_id' => $stack->getKey(),
            'tenant_id' => $stack->tenant_id,
            'brand_id' => $stack->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'observability_stack_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
