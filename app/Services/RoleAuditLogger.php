<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RoleAuditLogger
{
    public function created(Role $role, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'is_system' => $role->is_system,
                'description_digest' => $this->hashNullable($role->description),
            ],
        ];

        $this->persist($role, $actor, 'role.created', $payload);
        $this->logEvent('role.created', $role, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Role $role, ?User $actor, array $changes, float $startedAt): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($role, $actor, 'role.updated', $changes);
        $this->logEvent('role.updated', $role, $actor, $startedAt, $changes);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function deleted(Role $role, ?User $actor, array $permissions, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => $permissions,
                'is_system' => $role->is_system,
                'description_digest' => $this->hashNullable($role->description),
            ],
        ];

        $this->persist($role, $actor, 'role.deleted', $payload);
        $this->logEvent('role.deleted', $role, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Role $role, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $role->tenant_id,
            'brand_id' => null,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Role::class,
            'auditable_id' => $role->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Role $role, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $correlationId = request()?->header('X-Correlation-ID');

        Log::channel(config('logging.default'))->info($action, [
            'role_id' => $role->getKey(),
            'tenant_id' => $role->tenant_id,
            'permission_count' => $role->permissions->count(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'role_audit',
            'correlation_id' => $correlationId,
        ]);
    }

    protected function hashNullable(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
    }
}
