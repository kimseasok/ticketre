<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private readonly PermissionAuditLogger $auditLogger,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): Permission
    {
        $startedAt = microtime(true);
        $payload = $this->preparePayload($data, null, $actor);

        /** @var Permission $permission */
        $permission = DB::transaction(function () use ($payload): Permission {
            return Permission::create($payload);
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->created($permission, $actor, $startedAt);

        return $permission;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Permission $permission, array $data, ?User $actor): Permission
    {
        $startedAt = microtime(true);
        $payload = $this->preparePayload($data, $permission, $actor);
        $original = $this->originalSnapshot($permission);

        DB::transaction(function () use ($permission, $payload): void {
            $permission->fill($payload);
            $permission->save();
        });

        $permission->refresh();

        $changes = $this->diffChanges($original, $permission);

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->updated($permission, $actor, $changes, $startedAt);

        return $permission;
    }

    public function delete(Permission $permission, ?User $actor): void
    {
        if ($permission->is_system) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_PERMISSION_PROTECTED',
                    'message' => 'System permissions cannot be deleted.',
                ],
            ], 422));
        }

        $startedAt = microtime(true);

        DB::transaction(function () use ($permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->auditLogger->deleted($permission, $actor, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preparePayload(array $data, ?Permission $permission, ?User $actor): array
    {
        $attributes = Arr::only($data, ['name', 'description']);
        $attributes['guard_name'] = 'web';

        $tenantId = $permission?->tenant_id
            ?? $data['tenant_id']
            ?? $actor?->tenant_id
            ?? (app()->bound('currentTenant') ? app('currentTenant')?->getKey() : null);

        if ($tenantId !== null) {
            $attributes['tenant_id'] = $tenantId;
        }

        if (array_key_exists('brand_id', $data)) {
            $attributes['brand_id'] = $this->resolveBrandId($tenantId, $data['brand_id']);
        } elseif (! $permission) {
            $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
            $attributes['brand_id'] = $brand?->getKey();
        }

        if ($permission?->is_system) {
            $attributes['name'] = $permission->name;
            $attributes['brand_id'] = $permission->brand_id;
            $attributes['tenant_id'] = $permission->tenant_id;
            $attributes['is_system'] = true;
        } elseif (array_key_exists('is_system', $data)) {
            $attributes['is_system'] = (bool) $data['is_system'];
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function originalSnapshot(Permission $permission): array
    {
        return [
            'name' => $permission->name,
            'description' => $permission->description,
            'brand_id' => $permission->brand_id,
            'is_system' => $permission->is_system,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function diffChanges(array $original, Permission $permission): array
    {
        $changes = [];

        foreach ($original as $key => $value) {
            $current = $permission->{$key};

            if ($key === 'description') {
                $current = $current === null ? null : hash('sha256', (string) $current);
                $value = $value === null ? null : hash('sha256', (string) $value);
            }

            if ($current !== $value) {
                $changes[$key] = [
                    'old' => $key === 'description' ? $value : $original[$key],
                    'new' => $key === 'description' ? $current : $permission->{$key},
                ];
            }
        }

        return $changes;
    }

    protected function resolveBrandId(?int $tenantId, mixed $brandId): ?int
    {
        if ($brandId === null || $brandId === '') {
            return null;
        }

        $brandKey = (int) $brandId;

        $brand = Brand::query()
            ->where('id', $brandKey)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->first();

        if (! $brand) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_VALIDATION',
                    'message' => 'The selected brand is invalid for this tenant.',
                ],
            ], 422));
        }

        return $brand->getKey();
    }
}
