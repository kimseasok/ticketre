<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

class TenantPermissionRegistrar extends PermissionRegistrar
{
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $this->cacheKey = $this->resolveCacheKey();

        return parent::getPermissions($params, $onlyOne);
    }

    public function forgetCachedPermissions()
    {
        $this->cacheKey = $this->resolveCacheKey();

        return parent::forgetCachedPermissions();
    }

    protected function resolveCacheKey(): string
    {
        $baseKey = config('permission.cache.key', 'spatie.permission.cache');

        return sprintf('%s.%s', $baseKey, $this->resolveTenantCacheSuffix());
    }

    protected function resolveTenantCacheSuffix(): string
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant')
            ? (string) app('currentTenant')->getKey()
            : 'system';

        $brandId = app()->bound('currentBrand') && app('currentBrand')
            ? (string) app('currentBrand')->getKey()
            : 'global';

        return sprintf('tenant-%s.brand-%s', $this->sanitizeKey($tenantId), $this->sanitizeKey($brandId));
    }

    protected function sanitizeKey(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value) ?: 'unknown';
    }
}
