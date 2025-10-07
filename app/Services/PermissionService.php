<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly PermissionAuditLogger $auditLogger,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor = null): Permission
    {
        $startedAt = microtime(true);

        /** @var Permission $permission */
        $permission = $this->db->transaction(function () use ($payload) {
            $permission = new Permission();
            $permission->fill(Arr::except($payload, ['slug']));

            if (! empty($payload['slug'])) {
                $permission->slug = Str::slug((string) $payload['slug']);
            }

            $permission->save();

            return $permission;
        });

        $permission->refresh();
        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->created($permission, $actor, $startedAt);

        return $permission->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Permission $permission, array $payload, ?User $actor = null): Permission
    {
        if ($permission->is_system) {
            throw new RuntimeException('System permissions cannot be modified.');
        }

        $startedAt = microtime(true);
        $original = $this->snapshot($permission);

        $permission = $this->db->transaction(function () use ($permission, $payload) {
            $permission->fill(Arr::except($payload, ['slug']));

            if (array_key_exists('slug', $payload)) {
                $permission->slug = Str::slug((string) $payload['slug']);
            }

            $permission->save();

            return $permission;
        });

        $permission->refresh();
        $changes = $this->buildChangeSet($original, $permission);

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->updated($permission, $actor, $changes, $startedAt);

        return $permission->fresh();
    }

    public function delete(Permission $permission, ?User $actor = null): void
    {
        if ($permission->is_system) {
            throw new RuntimeException('System permissions cannot be deleted.');
        }

        $startedAt = microtime(true);
        $snapshot = $this->snapshot($permission);

        $this->db->transaction(function () use ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->deleted($permission, $actor, $snapshot, $startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(Permission $permission): array
    {
        return [
            'name' => $permission->name,
            'slug' => $permission->slug,
            'description_digest' => $permission->description ? hash('sha256', $permission->description) : null,
            'guard_name' => $permission->guard_name,
            'is_system' => $permission->is_system,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildChangeSet(array $original, Permission $permission): array
    {
        $current = $this->snapshot($permission);
        $changes = [];

        foreach ($current as $key => $value) {
            if ($original[$key] !== $value) {
                $changes[$key] = [
                    'old' => $original[$key],
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }
}
