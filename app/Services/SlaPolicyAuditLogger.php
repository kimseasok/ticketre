<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SlaPolicyAuditLogger
{
    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    public function created(SlaPolicy $policy, ?User $actor, array $targets, float $startedAt): void
    {
        $payload = [
            'snapshot' => $this->snapshot($policy, $targets),
        ];

        $this->persist($policy, $actor, 'sla-policy.created', $payload);
        $this->logEvent('sla-policy.created', $policy, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(SlaPolicy $policy, ?User $actor, array $changes, float $startedAt): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($policy, $actor, 'sla-policy.updated', $changes);
        $this->logEvent('sla-policy.updated', $policy, $actor, $startedAt, $changes);
    }

    public function deleted(SlaPolicy $policy, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => $this->snapshot($policy, $policy->targets->map->toArray()->all()),
        ];

        $this->persist($policy, $actor, 'sla-policy.deleted', $payload);
        $this->logEvent('sla-policy.deleted', $policy, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    protected function snapshot(SlaPolicy $policy, array $targets): array
    {
        return [
            'name' => $policy->name,
            'slug' => $policy->slug,
            'timezone' => $policy->timezone,
            'business_hours_count' => count($policy->business_hours ?? []),
            'holiday_count' => count($policy->holiday_exceptions ?? []),
            'default_first_response_minutes' => $policy->default_first_response_minutes,
            'default_resolution_minutes' => $policy->default_resolution_minutes,
            'enforce_business_hours' => $policy->enforce_business_hours,
            'targets' => array_map(fn (array $target) => [
                'channel' => $target['channel'] ?? null,
                'priority' => $target['priority'] ?? null,
                'first_response_minutes' => $target['first_response_minutes'] ?? null,
                'resolution_minutes' => $target['resolution_minutes'] ?? null,
                'use_business_hours' => $target['use_business_hours'] ?? null,
            ], $targets),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(SlaPolicy $policy, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $policy->tenant_id,
            'brand_id' => $policy->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => SlaPolicy::class,
            'auditable_id' => $policy->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, SlaPolicy $policy, ?User $actor, float $startedAt, array $payload): void
    {
        $correlationId = request()?->header('X-Correlation-ID');
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'sla_policy_id' => $policy->getKey(),
            'tenant_id' => $policy->tenant_id,
            'brand_id' => $policy->brand_id,
            'target_count' => $policy->targets()->count(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'sla_policy_audit',
            'correlation_id' => $correlationId,
        ]);
    }
}
