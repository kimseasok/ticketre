<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RbacEnforcementGapAnalysisAuditLogger
{
    public function created(RbacEnforcementGapAnalysis $analysis, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'analysis' => $this->snapshot($analysis),
        ];

        $this->persist($analysis, $actor, 'rbac_gap_analysis.created', $payload);
        $this->logEvent('rbac_gap_analysis.created', $analysis, $actor, $startedAt, $correlationId, $payload);
    }

    public function updated(RbacEnforcementGapAnalysis $analysis, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'analysis' => $this->snapshot($analysis),
        ];

        $this->persist($analysis, $actor, 'rbac_gap_analysis.updated', $payload);
        $this->logEvent('rbac_gap_analysis.updated', $analysis, $actor, $startedAt, $correlationId, $payload);
    }

    public function deleted(RbacEnforcementGapAnalysis $analysis, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'analysis' => $this->snapshot($analysis),
        ];

        $this->persist($analysis, $actor, 'rbac_gap_analysis.deleted', $payload);
        $this->logEvent('rbac_gap_analysis.deleted', $analysis, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(RbacEnforcementGapAnalysis $analysis): array
    {
        return [
            'title' => $analysis->title,
            'status' => $analysis->status,
            'analysis_date' => $analysis->analysis_date?->toAtomString(),
            'audit_matrix_count' => count($analysis->audit_matrix ?? []),
            'findings' => collect($analysis->findings ?? [])->map(function (array $finding) {
                return [
                    'priority' => $finding['priority'] ?? null,
                    'summary' => isset($finding['summary'])
                        ? Str::of((string) $finding['summary'])->limit(180, '')->value()
                        : null,
                    'summary_digest' => hash('sha256', (string) ($finding['summary'] ?? '')),
                    'eta_days' => $finding['eta_days'] ?? null,
                    'status' => $finding['status'] ?? null,
                ];
            })->all(),
            'remediation_plan_keys' => array_keys($analysis->remediation_plan ?? []),
            'owner_team' => $analysis->owner_team,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(RbacEnforcementGapAnalysis $analysis, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $analysis->tenant_id,
            'brand_id' => $analysis->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => RbacEnforcementGapAnalysis::class,
            'auditable_id' => $analysis->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, RbacEnforcementGapAnalysis $analysis, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'rbac_gap_analysis_id' => $analysis->getKey(),
            'tenant_id' => $analysis->tenant_id,
            'brand_id' => $analysis->brand_id,
            'user_id' => $actor?->getKey(),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'context' => 'rbac_gap_analysis',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
