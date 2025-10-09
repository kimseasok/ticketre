<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CiQualityGate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CiQualityGateAuditLogger
{
    public function created(CiQualityGate $gate, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($gate),
        ];

        $this->persist($gate, $actor, 'ci_quality_gate.created', $payload);
        $this->logEvent('ci_quality_gate.created', $gate, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(CiQualityGate $gate, ?User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($gate, $actor, 'ci_quality_gate.updated', $changes);
        $this->logEvent('ci_quality_gate.updated', $gate, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(CiQualityGate $gate, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($gate),
        ];

        $this->persist($gate, $actor, 'ci_quality_gate.deleted', $payload);
        $this->logEvent('ci_quality_gate.deleted', $gate, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(CiQualityGate $gate): array
    {
        return [
            'name_digest' => hash('sha256', (string) $gate->name),
            'slug' => $gate->slug,
            'coverage_threshold' => (float) $gate->coverage_threshold,
            'max_critical_vulnerabilities' => $gate->max_critical_vulnerabilities,
            'max_high_vulnerabilities' => $gate->max_high_vulnerabilities,
            'enforce_dependency_audit' => $gate->enforce_dependency_audit,
            'enforce_docker_build' => $gate->enforce_docker_build,
            'notifications_enabled' => $gate->notifications_enabled,
            'notify_channel_digest' => $gate->notifyChannelDigest(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(CiQualityGate $gate, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $gate->tenant_id,
            'brand_id' => $gate->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => CiQualityGate::class,
            'auditable_id' => $gate->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, CiQualityGate $gate, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'ci_quality_gate_id' => $gate->getKey(),
            'tenant_id' => $gate->tenant_id,
            'brand_id' => $gate->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'ci_quality_gate_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
