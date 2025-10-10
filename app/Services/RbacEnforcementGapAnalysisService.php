<?php

namespace App\Services;

use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RbacEnforcementGapAnalysisService
{
    public function __construct(private readonly RbacEnforcementGapAnalysisAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): RbacEnforcementGapAnalysis
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var RbacEnforcementGapAnalysis $analysis */
        $analysis = DB::transaction(fn () => RbacEnforcementGapAnalysis::create($attributes));
        $analysis->refresh();

        $this->auditLogger->created($analysis, $actor, $startedAt, $correlation);
        $this->logPerformance('rbac_gap_analysis.create', $analysis, $actor, $startedAt, $correlation);

        return $analysis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RbacEnforcementGapAnalysis $analysis, array $data, User $actor, ?string $correlationId = null): RbacEnforcementGapAnalysis
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);
        $attributes = $this->prepareAttributes($data, $analysis);

        DB::transaction(function () use ($analysis, $attributes): void {
            $analysis->fill($attributes);
            $analysis->save();
        });

        $analysis->refresh();

        $this->auditLogger->updated($analysis, $actor, $startedAt, $correlation);
        $this->logPerformance('rbac_gap_analysis.update', $analysis, $actor, $startedAt, $correlation);

        return $analysis;
    }

    public function delete(RbacEnforcementGapAnalysis $analysis, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $analysis->delete());

        $this->auditLogger->deleted($analysis, $actor, $startedAt, $correlation);
        $this->logPerformance('rbac_gap_analysis.delete', $analysis, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?RbacEnforcementGapAnalysis $existing = null): array
    {
        $attributes = Arr::only($data, [
            'tenant_id',
            'brand_id',
            'title',
            'slug',
            'status',
            'analysis_date',
            'audit_matrix',
            'findings',
            'remediation_plan',
            'review_minutes',
            'notes',
            'owner_team',
            'reference_id',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $existing?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $existing?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        $attributes['title'] = Str::of((string) ($attributes['title'] ?? $existing?->title ?? 'RBAC Gap Analysis'))
            ->limit(160, '')
            ->trim()
            ->value();

        if ($attributes['title'] === '') {
            $attributes['title'] = 'RBAC Gap Analysis';
        }

        if (array_key_exists('slug', $attributes) && $attributes['slug']) {
            $attributes['slug'] = Str::of((string) $attributes['slug'])->lower()->slug()->limit(160, '')->value();
        }

        $status = Str::of((string) ($attributes['status'] ?? $existing?->status ?? 'draft'))
            ->lower()
            ->slug('_')
            ->value();
        if (! in_array($status, RbacEnforcementGapAnalysis::STATUSES, true)) {
            $status = $existing?->status ?? 'draft';
        }
        $attributes['status'] = $status;

        if (array_key_exists('analysis_date', $attributes) && $attributes['analysis_date']) {
            $attributes['analysis_date'] = Carbon::parse($attributes['analysis_date']);
        }

        foreach (['audit_matrix', 'findings'] as $arrayKey) {
            if (array_key_exists($arrayKey, $attributes)) {
                $attributes[$arrayKey] = $attributes[$arrayKey] === null
                    ? []
                    : Arr::wrap($attributes[$arrayKey]);
            }
        }

        if (array_key_exists('remediation_plan', $attributes)) {
            $attributes['remediation_plan'] = $attributes['remediation_plan'] === null
                ? null
                : (array) $attributes['remediation_plan'];
        }

        if (array_key_exists('review_minutes', $attributes)) {
            $attributes['review_minutes'] = Str::of((string) $attributes['review_minutes'])->limit(4000, '')->value();
        }

        if (array_key_exists('notes', $attributes)) {
            $attributes['notes'] = $attributes['notes'] === null
                ? null
                : Str::of((string) $attributes['notes'])->limit(2000, '')->value();
        }

        if (array_key_exists('owner_team', $attributes) && $attributes['owner_team'] !== null) {
            $attributes['owner_team'] = Str::of((string) $attributes['owner_team'])->limit(120, '')->value();
        }

        if (array_key_exists('reference_id', $attributes) && $attributes['reference_id'] !== null) {
            $attributes['reference_id'] = Str::of((string) $attributes['reference_id'])->limit(64, '')->value();
        }

        return $attributes;
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        $value = $correlationId ?: (string) Str::uuid();

        return Str::of($value)->limit(64, '')->value();
    }

    protected function logPerformance(string $action, RbacEnforcementGapAnalysis $analysis, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'rbac_gap_analysis_id' => $analysis->getKey(),
            'tenant_id' => $analysis->tenant_id,
            'brand_id' => $analysis->brand_id,
            'status' => $analysis->status,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'rbac_gap_analysis',
            'audit_matrix_count' => count($analysis->audit_matrix ?? []),
            'findings_count' => count($analysis->findings ?? []),
            'review_minutes_digest' => hash('sha256', (string) $analysis->review_minutes),
        ]);
    }
}
