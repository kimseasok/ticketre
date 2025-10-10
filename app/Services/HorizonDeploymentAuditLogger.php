<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\HorizonDeployment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class HorizonDeploymentAuditLogger
{
    public function created(HorizonDeployment $deployment, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = ['snapshot' => $this->snapshot($deployment)];

        $this->persist($deployment, $actor, 'horizon.deployment.created', $payload, $correlationId);
        $this->logEvent('horizon.deployment.created', $deployment, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(HorizonDeployment $deployment, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($deployment, $actor, 'horizon.deployment.updated', $changes, $correlationId);
        $this->logEvent('horizon.deployment.updated', $deployment, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(HorizonDeployment $deployment, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = ['snapshot' => $this->snapshot($deployment)];

        $this->persist($deployment, $actor, 'horizon.deployment.deleted', $payload, $correlationId);
        $this->logEvent('horizon.deployment.deleted', $deployment, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(HorizonDeployment $deployment): array
    {
        return [
            'name' => $deployment->name,
            'slug' => $deployment->slug,
            'domain_digest' => $deployment->domainDigest(),
            'auth_guard' => $deployment->auth_guard,
            'horizon_connection' => $deployment->horizon_connection,
            'uses_tls' => $deployment->uses_tls,
            'supervisor_count' => $deployment->supervisorCount(),
            'last_health_status' => $deployment->last_health_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(HorizonDeployment $deployment, User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $deployment->tenant_id,
            'brand_id' => $deployment->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => HorizonDeployment::class,
            'auditable_id' => $deployment->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, HorizonDeployment $deployment, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'horizon_deployment_id' => $deployment->getKey(),
            'tenant_id' => $deployment->tenant_id,
            'brand_id' => $deployment->brand_id,
            'domain_digest' => $deployment->domainDigest(),
            'supervisor_count' => $deployment->supervisorCount(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'horizon_deployment_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
