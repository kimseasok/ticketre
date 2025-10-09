<?php

namespace App\Services;

use App\Models\CiQualityGate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CiQualityGateService
{
    public function __construct(private readonly CiQualityGateAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): CiQualityGate
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var CiQualityGate $gate */
        $gate = DB::transaction(fn () => CiQualityGate::create($attributes));
        $gate->refresh();

        $this->auditLogger->created($gate, $actor, $startedAt, $correlation);
        $this->logPerformance('ci_quality_gate.create', $gate, $actor, $startedAt, $correlation);

        return $gate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CiQualityGate $gate, array $data, User $actor, ?string $correlationId = null): CiQualityGate
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $gate);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($gate->getOriginal(), [
            'name',
            'slug',
            'coverage_threshold',
            'max_critical_vulnerabilities',
            'max_high_vulnerabilities',
            'enforce_dependency_audit',
            'enforce_docker_build',
            'notifications_enabled',
            'notify_channel',
            'brand_id',
        ]);

        $dirty = [];

        DB::transaction(function () use ($gate, $attributes, &$dirty) {
            $gate->fill($attributes);
            $dirty = Arr::except($gate->getDirty(), ['updated_at']);
            $gate->save();
        });

        $gate->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            if ($field === 'notify_channel') {
                $changes['notify_channel_digest'] = [
                    'old' => $original['notify_channel'] ? hash('sha256', (string) $original['notify_channel']) : null,
                    'new' => $gate->notifyChannelDigest(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $gate->{$field},
            ];
        }

        $this->auditLogger->updated($gate, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('ci_quality_gate.update', $gate, $actor, $startedAt, $correlation);

        return $gate;
    }

    public function delete(CiQualityGate $gate, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $gate->delete());

        $this->auditLogger->deleted($gate, $actor, $startedAt, $correlation);
        $this->logPerformance('ci_quality_gate.delete', $gate, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?CiQualityGate $gate = null): array
    {
        $attributes = Arr::only($data, [
            'name',
            'slug',
            'coverage_threshold',
            'max_critical_vulnerabilities',
            'max_high_vulnerabilities',
            'enforce_dependency_audit',
            'enforce_docker_build',
            'notifications_enabled',
            'notify_channel',
            'brand_id',
            'metadata',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $gate?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $gate?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        if (! array_key_exists('name', $attributes) && ! $gate) {
            $attributes['name'] = 'CI Quality Gate '.Str::random(6);
        }

        if (! array_key_exists('slug', $attributes) || empty($attributes['slug'])) {
            $source = $attributes['name'] ?? $gate?->name ?? 'ci-quality-gate';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        if (isset($attributes['notify_channel'])) {
            $attributes['notify_channel'] = $this->sanitizeChannel($attributes['notify_channel']);
        }

        if (! isset($attributes['coverage_threshold'])) {
            $attributes['coverage_threshold'] = $gate?->coverage_threshold ?? 85.00;
        }

        if (! isset($attributes['metadata'])) {
            $attributes['metadata'] = $gate?->metadata ?? [];
        }

        return $attributes;
    }

    protected function sanitizeChannel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_\-:#]/', '', (string) $value) ?? '';
        $sanitized = ltrim($sanitized, '#');

        return $sanitized !== '' ? '#'.$sanitized : null;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function logPerformance(string $action, CiQualityGate $gate, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'ci_quality_gate_id' => $gate->getKey(),
            'tenant_id' => $gate->tenant_id,
            'brand_id' => $gate->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'ci_quality_gate_service',
        ]);
    }
}
