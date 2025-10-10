<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PermissionCoverageReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PermissionCoverageReportAuditLogger
{
    public function created(PermissionCoverageReport $report, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'coverage' => $this->snapshot($report),
        ];

        $this->persist($report, $actor, 'permission_coverage_report.created', $payload);
        $this->logEvent('permission_coverage_report.created', $report, $actor, $startedAt, $correlationId, $payload);
    }

    public function updated(PermissionCoverageReport $report, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'coverage' => $this->snapshot($report),
        ];

        $this->persist($report, $actor, 'permission_coverage_report.updated', $payload);
        $this->logEvent('permission_coverage_report.updated', $report, $actor, $startedAt, $correlationId, $payload);
    }

    public function deleted(PermissionCoverageReport $report, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'coverage' => $this->snapshot($report),
        ];

        $this->persist($report, $actor, 'permission_coverage_report.deleted', $payload);
        $this->logEvent('permission_coverage_report.deleted', $report, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(PermissionCoverageReport $report): array
    {
        return [
            'module' => $report->module,
            'coverage' => $report->coverage,
            'total_routes' => $report->total_routes,
            'unguarded_routes' => $report->unguarded_routes,
            'unguarded_digests' => collect($report->unguarded_paths ?? [])->pluck('uri_digest')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(PermissionCoverageReport $report, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $report->tenant_id,
            'brand_id' => $report->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => PermissionCoverageReport::class,
            'auditable_id' => $report->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, PermissionCoverageReport $report, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'permission_coverage_report_id' => $report->getKey(),
            'tenant_id' => $report->tenant_id,
            'brand_id' => $report->brand_id,
            'user_id' => $actor?->getKey(),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'context' => 'permission_coverage_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
