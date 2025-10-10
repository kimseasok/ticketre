<?php

namespace App\Services;

use App\Models\PermissionCoverageReport;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PermissionCoverageReportService
{
    public function __construct(
        private readonly PermissionCoverageReportAuditLogger $auditLogger,
        private readonly RoutePermissionCoverageAnalyzer $analyzer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): PermissionCoverageReport
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var PermissionCoverageReport $report */
        $report = DB::transaction(fn () => PermissionCoverageReport::create($attributes));
        $report->refresh();

        $this->auditLogger->created($report, $actor, $startedAt, $correlation);
        $this->logPerformance('permission_coverage_report.create', $report, $actor, $startedAt, $correlation);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PermissionCoverageReport $report, array $data, User $actor, ?string $correlationId = null): PermissionCoverageReport
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);
        $attributes = $this->prepareAttributes($data, $report);

        DB::transaction(function () use ($report, $attributes): void {
            $report->fill($attributes);
            $report->save();
        });

        $report->refresh();

        $this->auditLogger->updated($report, $actor, $startedAt, $correlation);
        $this->logPerformance('permission_coverage_report.update', $report, $actor, $startedAt, $correlation);

        return $report;
    }

    public function delete(PermissionCoverageReport $report, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $report->delete());

        $this->auditLogger->deleted($report, $actor, $startedAt, $correlation);
        $this->logPerformance('permission_coverage_report.delete', $report, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?PermissionCoverageReport $existing = null): array
    {
        $attributes = Arr::only($data, ['tenant_id', 'brand_id', 'module', 'metadata', 'notes']);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $existing?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $existing?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        $attributes['module'] = Str::of((string) ($attributes['module'] ?? $existing?->module ?? 'api'))->lower()->slug('_')->value();

        if (! in_array($attributes['module'], PermissionCoverageReport::MODULES, true)) {
            throw new \InvalidArgumentException('Unsupported module for permission coverage report.');
        }

        if (array_key_exists('notes', $attributes)) {
            $attributes['notes'] = $attributes['notes'] === null
                ? null
                : Str::of((string) $attributes['notes'])->limit(1024, '')->value();
        } else {
            $attributes['notes'] = $existing?->notes;
        }

        if (array_key_exists('metadata', $attributes)) {
            $attributes['metadata'] = $attributes['metadata'] === null
                ? []
                : $this->sanitizeMetadata((array) $attributes['metadata']);
        } else {
            $attributes['metadata'] = $this->sanitizeMetadata($existing?->metadata ?? []);
        }

        $coverage = $this->analyzer->analyzeModule($attributes['module']);
        $attributes['total_routes'] = $coverage['total_routes'];
        $attributes['guarded_routes'] = $coverage['guarded_routes'];
        $attributes['unguarded_routes'] = $coverage['unguarded_routes'];
        $attributes['coverage'] = $coverage['coverage'];
        $attributes['unguarded_paths'] = $coverage['unguarded_paths'];
        $attributes['generated_at'] = now();

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        $allowed = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = Str::of((string) $key)->limit(120, '')->snake()->value();
            if ($normalizedKey === '') {
                continue;
            }

            $allowed[$normalizedKey] = is_scalar($value) || $value === null
                ? $this->normalizeScalar($value)
                : Arr::wrap($value);
        }

        return $allowed;
    }

    protected function normalizeScalar(mixed $value): string|int|float|bool|null
    {
        if (is_string($value)) {
            return Str::of($value)->limit(255, '')->value();
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return Str::of(json_encode($value))->limit(255, '')->value();
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        $value = $correlationId ?: (string) Str::uuid();

        return Str::of($value)->limit(64, '')->value();
    }

    protected function logPerformance(string $action, PermissionCoverageReport $report, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'permission_coverage_report_id' => $report->getKey(),
            'tenant_id' => $report->tenant_id,
            'brand_id' => $report->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'permission_coverage_audit',
            'module' => $report->module,
            'unguarded_count' => $report->unguarded_routes,
        ]);
    }
}
