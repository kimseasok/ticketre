<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

class TenantPermissionRegistrar extends PermissionRegistrar
{
    public function useContext(?Tenant $tenant, ?Brand $brand = null): void
    {
        parent::initializeCache();

        $this->cacheKey = sprintf(
            '%s::tenant:%s::brand:%s',
            config('permission.cache.key'),
            $tenant?->getKey() ?? 'global',
            $brand?->getKey() ?? 'global'
        );
    }

    public function forgetCachedPermissions()
    {
        $this->useContext(
            app()->bound('currentTenant') ? app('currentTenant') : null,
            app()->bound('currentBrand') ? app('currentBrand') : null,
        );

        return parent::forgetCachedPermissions();
    }

    protected function getPermissionsWithRoles(): Collection
    {
        $query = $this->permissionClass::query()->with('roles');

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($tenant) {
            $query->where('tenant_id', $tenant->getKey());
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($brand) {
            $query->where(function (Builder $builder) use ($brand): void {
                $builder->whereNull('brand_id')->orWhere('brand_id', $brand->getKey());
            });
        }

        return $query->orderBy('name')->get();
    }
}
