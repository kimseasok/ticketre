<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(private readonly RoleAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): Role
    {
        $startedAt = microtime(true);

        $payload = $this->preparePayload($data);
        $permissions = $payload['permissions'];

        $role = DB::transaction(function () use ($payload, $permissions) {
            /** @var Role $role */
            $role = Role::create($payload['attributes']);
            $role->syncPermissions($permissions);

            return $role->fresh(['permissions']);
        });

        $this->auditLogger->created($role, $actor, $startedAt);

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data, ?User $actor): Role
    {
        $startedAt = microtime(true);

        $payload = $this->preparePayload($data, $role);
        $permissions = $payload['permissions'];

        $original = [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        $role = DB::transaction(function () use ($role, $payload, $permissions) {
            $role->fill($payload['attributes']);
            $dirty = Arr::except($role->getDirty(), ['updated_at']);
            $role->save();

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            return [$role->fresh(['permissions']), $dirty];
        });

        /** @var array{0: Role, 1: array<string, mixed>} $role */
        [$updatedRole, $dirty] = $role;

        $changes = $this->buildChangeSet($original, $updatedRole, $dirty, $permissions);

        $this->auditLogger->updated($updatedRole, $actor, $changes, $startedAt);

        return $updatedRole;
    }

    public function delete(Role $role, ?User $actor): void
    {
        if ($role->is_system) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_ROLE_PROTECTED',
                    'message' => 'System roles cannot be deleted.',
                ],
            ], 422));
        }

        $startedAt = microtime(true);
        $role->loadMissing('permissions');
        $permissionSnapshot = $role->permissions->pluck('name')->values()->all();

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        $this->auditLogger->deleted($role, $actor, $permissionSnapshot, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, permissions: array<int, string>|null}
     */
    protected function preparePayload(array $data, ?Role $role = null): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'description']);
        $attributes['guard_name'] = 'web';

        if ($role) {
            $attributes['tenant_id'] = $role->tenant_id;
        } else {
            $tenantId = $data['tenant_id']
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);

            if ($tenantId !== null) {
                $attributes['tenant_id'] = $tenantId;
            }
        }

        if (! array_key_exists('slug', $attributes) || empty($attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['name'] ?? ($role?->name ?? '')) ?: Str::uuid()->toString();
        }

        if (array_key_exists('is_system', $data)) {
            $attributes['is_system'] = (bool) $data['is_system'];
        }

        if ($role && $role->is_system) {
            $attributes['slug'] = $role->slug;
            $attributes['is_system'] = true;
        }

        $permissions = $role ? null : [];

        if (array_key_exists('permissions', $data)) {
            /** @var array<int, string> $permissionNames */
            $permissionNames = array_values(array_unique(array_map('strval', (array) $data['permissions'])));
            $permissions = $permissionNames;
        }

        return [
            'attributes' => $attributes,
            'permissions' => $permissions,
        ];
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $dirty
     * @param  array<int, string>|null  $permissions
     * @return array<string, mixed>
     */
    protected function buildChangeSet(array $original, Role $role, array $dirty, ?array $permissions): array
    {
        $changes = [];

        foreach ($dirty as $field => $_value) {
            if ($field === 'description') {
                $changes['description_digest'] = [
                    'old' => $original['description'] ? hash('sha256', (string) $original['description']) : null,
                    'new' => $role->description ? hash('sha256', (string) $role->description) : null,
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $role->{$field},
            ];
        }

        if ($permissions !== null) {
            $newPermissions = $role->permissions->pluck('name')->sort()->values()->all();

            if ($newPermissions !== $original['permissions']) {
                $changes['permissions'] = [
                    'old' => $original['permissions'],
                    'new' => $newPermissions,
                ];
            }
        }

        return $changes;
    }
}
