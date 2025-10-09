<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PermissionAuditLogger
{
    public function created(Permission $permission, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name_digest' => $this->hashNullable($permission->name),
                'slug' => $permission->slug,
                'brand_id' => $permission->brand_id,
                'is_system' => $permission->is_system,
                'description_digest' => $this->hashNullable($permission->description),
            ],
        ];

        $this->persist($permission, $actor, 'permission.created', $payload);
        $this->logEvent('permission.created', $permission, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Permission $permission, ?User $actor, array $changes, float $startedAt): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($permission, $actor, 'permission.updated', $changes);
        $this->logEvent('permission.updated', $permission, $actor, $startedAt, $changes);
    }

    public function deleted(Permission $permission, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name_digest' => $this->hashNullable($permission->name),
                'slug' => $permission->slug,
                'brand_id' => $permission->brand_id,
                'is_system' => $permission->is_system,
                'description_digest' => $this->hashNullable($permission->description),
            ],
        ];

        $this->persist($permission, $actor, 'permission.deleted', $payload);
        $this->logEvent('permission.deleted', $permission, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Permission $permission, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $permission->tenant_id,
            'brand_id' => $permission->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Permission::class,
            'auditable_id' => $permission->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Permission $permission, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $correlationId = request()?->header('X-Correlation-ID');

        Log::channel(config('logging.default'))->info($action, [
            'permission_id' => $permission->getKey(),
            'tenant_id' => $permission->tenant_id,
            'brand_id' => $permission->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'permission_audit',
            'correlation_id' => $correlationId,
            'payload_keys' => array_keys($payload),
        ]);
    }

    protected function hashNullable(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
    }
}
