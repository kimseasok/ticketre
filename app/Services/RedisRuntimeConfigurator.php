<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\RedisConfiguration;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RedisRuntimeConfigurator
{
    public function apply(?Tenant $tenant, ?Brand $brand, ?string $correlationId = null): void
    {
        $correlation = $this->resolveCorrelationId($correlationId);

        if (! $tenant) {
            $this->applyEnvironmentDefaults($correlation, null, $brand?->getKey());

            return;
        }

        $configuration = $this->resolveConfiguration($tenant, $brand);

        if (! $configuration) {
            $this->applyEnvironmentDefaults($correlation, $tenant->getKey(), $brand?->getKey());

            return;
        }

        $this->applyConfiguration($configuration, $correlation);
    }

    public function applyForTenant(int $tenantId, ?int $brandId = null, ?string $correlationId = null): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $brand = $brandId ? Brand::withoutGlobalScopes()->find($brandId) : null;

        $this->apply($tenant, $brand, $correlationId);
    }

    protected function resolveConfiguration(Tenant $tenant, ?Brand $brand): ?RedisConfiguration
    {
        $baseQuery = RedisConfiguration::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('updated_at');

        if ($brand) {
            $brandSpecific = (clone $baseQuery)->where('brand_id', $brand->getKey())->first();

            if ($brandSpecific) {
                return $brandSpecific;
            }
        }

        return (clone $baseQuery)->whereNull('brand_id')->first();
    }

    protected function applyEnvironmentDefaults(string $correlationId, ?int $tenantId, ?int $brandId): void
    {
        $cacheConnection = Config::get('cache.stores.redis-fallback.connection', 'cache');
        $sessionConnection = Config::get('session.connection', Config::get('cache.stores.redis-fallback.connection', 'cache'));

        Log::info('redis.configuration.env_fallback', [
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'cache_connection' => $cacheConnection,
            'session_connection' => $sessionConnection,
        ]);

        Cache::forgetDriver('redis-fallback');
        Cache::store('redis-fallback');
    }

    protected function applyConfiguration(RedisConfiguration $configuration, string $correlationId): void
    {
        $cachePassword = $this->decryptSecret($configuration->cache_auth_secret);
        $sessionPassword = $this->decryptSecret($configuration->session_auth_secret);

        $cacheConnection = $configuration->cache_connection_name ?: 'cache';
        $sessionConnection = $configuration->session_connection_name ?: $cacheConnection;

        Config::set('cache.stores.redis-fallback.connection', $cacheConnection);
        Config::set('cache.stores.redis-fallback.fallback', $configuration->fallback_store ?: 'file');

        if ($configuration->cache_prefix) {
            Config::set('cache.prefix', $configuration->cache_prefix);
        }

        Config::set("database.redis.{$cacheConnection}.host", $configuration->cache_host);
        Config::set("database.redis.{$cacheConnection}.port", $configuration->cache_port);
        Config::set("database.redis.{$cacheConnection}.database", $configuration->cache_database);
        Config::set("database.redis.{$cacheConnection}.password", $cachePassword);
        Config::set("database.redis.{$cacheConnection}.options", array_filter([
            'parameters' => [
                'scheme' => $configuration->cache_tls ? 'tls' : 'tcp',
            ],
            'ssl' => $configuration->cache_tls ? [] : null,
        ]));

        Config::set('database.redis.options', array_merge(
            Config::get('database.redis.options', []),
            $configuration->options ?? []
        ));

        if ($configuration->use_for_sessions) {
            Config::set('session.driver', 'redis-fallback');
            Config::set('session.store', 'redis-fallback');
            Config::set('session.connection', $sessionConnection);
            Config::set('session.lifetime', $configuration->session_lifetime_minutes);
        }

        Config::set("database.redis.{$sessionConnection}.host", $configuration->session_host);
        Config::set("database.redis.{$sessionConnection}.port", $configuration->session_port);
        Config::set("database.redis.{$sessionConnection}.database", $configuration->session_database);
        Config::set("database.redis.{$sessionConnection}.password", $sessionPassword ?? $cachePassword);
        Config::set("database.redis.{$sessionConnection}.options", array_filter([
            'parameters' => [
                'scheme' => $configuration->session_tls ? 'tls' : 'tcp',
            ],
            'ssl' => $configuration->session_tls ? [] : null,
        ]));

        Cache::forgetDriver('redis-fallback');
        Cache::store('redis-fallback');

        Log::info('redis.configuration.applied', [
            'correlation_id' => $correlationId,
            'tenant_id' => $configuration->tenant_id,
            'brand_id' => $configuration->brand_id,
            'cache_connection' => $cacheConnection,
            'session_connection' => $sessionConnection,
            'cache_host_digest' => $configuration->cacheHostDigest(),
            'session_host_digest' => $configuration->sessionHostDigest(),
            'use_for_cache' => $configuration->use_for_cache,
            'use_for_sessions' => $configuration->use_for_sessions,
            'fallback_store' => $configuration->fallback_store,
        ]);
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        $value = $correlationId
            ?? request()?->headers->get('X-Correlation-ID')
            ?? request()?->header('X-Correlation-ID')
            ?? (string) Str::uuid();

        return Str::limit($value, 64, '');
    }

    protected function decryptSecret(?string $secret): ?string
    {
        if (! $secret) {
            return null;
        }

        try {
            return Crypt::decryptString($secret);
        } catch (Throwable $exception) {
            Log::warning('redis.configuration.secret_decrypt_failed', [
                'correlation_id' => $this->resolveCorrelationId(null),
                'exception' => get_class($exception),
                'message_digest' => hash('sha256', (string) $exception->getMessage()),
            ]);

            return null;
        }
    }
}
