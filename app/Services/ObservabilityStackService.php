<?php

namespace App\Services;

use App\Models\ObservabilityStack;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ObservabilityStackService
{
    public function __construct(
        private readonly ObservabilityStackAuditLogger $auditLogger,
        private readonly ObservabilityMetricRecorder $metricRecorder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): ObservabilityStack
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var ObservabilityStack $stack */
        $stack = DB::transaction(fn () => ObservabilityStack::create($attributes));
        $stack->refresh();

        $this->auditLogger->created($stack, $actor, $startedAt, $correlation);
        $this->recordMetrics($stack, 'create', $startedAt);
        $this->logPerformance('observability_stack.create', $stack, $actor, $startedAt, $correlation);

        return $stack;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ObservabilityStack $stack, array $data, User $actor, ?string $correlationId = null): ObservabilityStack
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $stack);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($stack->getOriginal(), [
            'name',
            'slug',
            'status',
            'logs_tool',
            'metrics_tool',
            'alerts_tool',
            'log_retention_days',
            'metric_retention_days',
            'trace_retention_days',
            'estimated_monthly_cost',
            'trace_sampling_strategy',
            'decision_matrix',
            'security_notes',
            'compliance_notes',
            'brand_id',
            'metadata',
        ]);

        $dirty = [];

        DB::transaction(function () use ($stack, $attributes, &$dirty): void {
            $stack->fill($attributes);
            $dirty = Arr::except($stack->getDirty(), ['updated_at']);
            $stack->save();
        });

        $stack->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            if (in_array($field, ['name', 'logs_tool', 'metrics_tool'], true)) {
                $changes[$field.'_digest'] = [
                    'old' => isset($original[$field]) ? hash('sha256', (string) $original[$field]) : null,
                    'new' => match ($field) {
                        'name' => $stack->nameDigest(),
                        'logs_tool' => $stack->logsToolDigest(),
                        'metrics_tool' => $stack->metricsToolDigest(),
                        default => null,
                    },
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $stack->{$field},
            ];
        }

        if ($changes !== []) {
            $this->auditLogger->updated($stack, $actor, $changes, $startedAt, $correlation);
            $this->recordMetrics($stack, 'update', $startedAt);
            $this->logPerformance('observability_stack.update', $stack, $actor, $startedAt, $correlation, array_keys($changes));
        }

        return $stack;
    }

    public function delete(ObservabilityStack $stack, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $stack->delete());

        $this->auditLogger->deleted($stack, $actor, $startedAt, $correlation);
        $this->recordMetrics($stack, 'delete', $startedAt);
        $this->logPerformance('observability_stack.delete', $stack, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?ObservabilityStack $stack = null): array
    {
        $attributes = Arr::only($data, [
            'name',
            'slug',
            'status',
            'logs_tool',
            'metrics_tool',
            'alerts_tool',
            'log_retention_days',
            'metric_retention_days',
            'trace_retention_days',
            'estimated_monthly_cost',
            'trace_sampling_strategy',
            'decision_matrix',
            'security_notes',
            'compliance_notes',
            'metadata',
            'brand_id',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $stack?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $stack?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        if (isset($attributes['name'])) {
            $attributes['name'] = trim((string) $attributes['name']);
        } elseif (! $stack) {
            $attributes['name'] = 'Observability Stack '.Str::upper(Str::random(4));
        }

        if (! isset($attributes['slug']) || $attributes['slug'] === null || $attributes['slug'] === '') {
            $source = $attributes['name'] ?? $stack?->name ?? 'observability-stack';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        if (isset($attributes['status'])) {
            $attributes['status'] = strtolower((string) $attributes['status']);
        } elseif (! $stack) {
            $attributes['status'] = 'evaluating';
        }

        foreach (['logs_tool', 'metrics_tool', 'alerts_tool'] as $toolField) {
            if (isset($attributes[$toolField])) {
                $attributes[$toolField] = strtolower(str_replace(' ', '-', (string) $attributes[$toolField]));
            } elseif (! $stack) {
                $attributes[$toolField] = match ($toolField) {
                    'logs_tool' => 'elk',
                    'metrics_tool' => 'prometheus',
                    'alerts_tool' => 'grafana-alerting',
                    default => null,
                };
            }
        }

        foreach (['log_retention_days', 'metric_retention_days', 'trace_retention_days'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $value = $attributes[$field];
                $attributes[$field] = $value === null ? null : max(0, (int) $value);
            } elseif (! $stack && $field !== 'trace_retention_days') {
                $attributes[$field] = 30;
            }
        }

        if (array_key_exists('estimated_monthly_cost', $attributes)) {
            $attributes['estimated_monthly_cost'] = $attributes['estimated_monthly_cost'] === null
                ? null
                : round((float) $attributes['estimated_monthly_cost'], 2);
        } elseif (! $stack) {
            $attributes['estimated_monthly_cost'] = 0.0;
        }

        if (array_key_exists('trace_sampling_strategy', $attributes) && $attributes['trace_sampling_strategy'] !== null) {
            $attributes['trace_sampling_strategy'] = Str::limit(trim((string) $attributes['trace_sampling_strategy']), 255, '');
        }

        if (array_key_exists('decision_matrix', $attributes)) {
            $attributes['decision_matrix'] = $attributes['decision_matrix'] === null
                ? null
                : $this->normalizeDecisionMatrix((array) $attributes['decision_matrix']);
        } elseif (! $stack) {
            $attributes['decision_matrix'] = null;
        }

        if (array_key_exists('security_notes', $attributes) && $attributes['security_notes'] !== null) {
            $attributes['security_notes'] = Str::limit(strip_tags((string) $attributes['security_notes']), 4000, '');
        }

        if (array_key_exists('compliance_notes', $attributes) && $attributes['compliance_notes'] !== null) {
            $attributes['compliance_notes'] = Str::limit(strip_tags((string) $attributes['compliance_notes']), 4000, '');
        }

        if (! isset($attributes['metadata'])) {
            $attributes['metadata'] = $stack?->metadata ?? [];
        } elseif (! is_array($attributes['metadata'])) {
            $attributes['metadata'] = (array) $attributes['metadata'];
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDecisionMatrix(array $matrix): array
    {
        $normalized = [];

        foreach ($matrix as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $option = Str::limit(trim((string) ($entry['option'] ?? '')), 255, '');
            if ($option === '') {
                continue;
            }

            $normalized[] = [
                'option' => $option,
                'monthly_cost' => round((float) ($entry['monthly_cost'] ?? 0), 2),
                'scalability' => Str::limit(strip_tags((string) ($entry['scalability'] ?? '')), 1024, ''),
                'notes' => isset($entry['notes']) && $entry['notes'] !== null
                    ? Str::limit(strip_tags((string) $entry['notes']), 2048, '')
                    : null,
            ];
        }

        return $normalized;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function recordMetrics(ObservabilityStack $stack, string $action, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        $labels = [
            'tenant_id' => (string) $stack->tenant_id,
            'brand_id' => $stack->brand_id ? (string) $stack->brand_id : 'unscoped',
            'status' => (string) $stack->status,
            'action' => $action,
        ];

        $this->metricRecorder->incrementCounter('observability_stack_events_total', $labels);
        $this->metricRecorder->observeSummary('observability_stack_duration_ms', $labels, $durationMs);
        $this->metricRecorder->setGauge('observability_stack_selected_cost', [
            'tenant_id' => (string) $stack->tenant_id,
            'brand_id' => $stack->brand_id ? (string) $stack->brand_id : 'unscoped',
        ], (float) ($stack->estimated_monthly_cost ?? 0));
    }

    /**
     * @param  array<int, string>|null  $fields
     */
    protected function logPerformance(string $action, ObservabilityStack $stack, ?User $actor, float $startedAt, string $correlationId, ?array $fields = null): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'observability_stack_id' => $stack->getKey(),
            'tenant_id' => $stack->tenant_id,
            'brand_id' => $stack->brand_id,
            'status' => $stack->status,
            'estimated_monthly_cost' => $stack->estimated_monthly_cost !== null
                ? (float) $stack->estimated_monthly_cost
                : null,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'observability_stack',
            'changed_fields' => $fields,
            'name_digest' => $stack->nameDigest(),
            'logs_tool_digest' => $stack->logsToolDigest(),
            'metrics_tool_digest' => $stack->metricsToolDigest(),
        ]);
    }
}
