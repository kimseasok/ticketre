<?php

namespace App\Services;

use App\Models\RedisConfiguration;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RedisConfigurationService
{
    public function __construct(
        private readonly RedisConfigurationAuditLogger $auditLogger,
        private readonly RedisRuntimeConfigurator $runtimeConfigurator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): RedisConfiguration
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var RedisConfiguration $configuration */
        $configuration = DB::transaction(fn () => RedisConfiguration::create($attributes));
        $configuration->refresh();

        $this->auditLogger->created($configuration, $actor, $startedAt, $correlation);
        $this->logPerformance('redis_configuration.create', $configuration, $actor, $startedAt, $correlation);
        $this->runtimeConfigurator->applyForTenant($configuration->tenant_id, $configuration->brand_id, $correlation);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RedisConfiguration $configuration, array $data, User $actor, ?string $correlationId = null): RedisConfiguration
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $configuration);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($configuration->getOriginal(), [
            'name',
            'slug',
            'cache_connection_name',
            'cache_host',
            'cache_port',
            'cache_database',
            'cache_tls',
            'cache_prefix',
            'session_connection_name',
            'session_host',
            'session_port',
            'session_database',
            'session_tls',
            'session_lifetime_minutes',
            'use_for_cache',
            'use_for_sessions',
            'is_active',
            'fallback_store',
        ]);

        $dirty = [];

        DB::transaction(function () use ($configuration, $attributes, &$dirty): void {
            $configuration->fill($attributes);
            $dirty = Arr::except($configuration->getDirty(), ['updated_at']);
            $configuration->save();
        });

        $configuration->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            if (in_array($field, ['cache_auth_secret', 'session_auth_secret'], true)) {
                $changes[$field] = [
                    'old' => empty($original[$field] ?? null) ? null : 'set',
                    'new' => empty($configuration->{$field}) ? null : 'set',
                ];

                continue;
            }

            if (in_array($field, ['cache_host', 'session_host'], true)) {
                $changes[$field.'_digest'] = [
                    'old' => hash('sha256', (string) ($original[$field] ?? '')),
                    'new' => $field === 'cache_host'
                        ? $configuration->cacheHostDigest()
                        : $configuration->sessionHostDigest(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $configuration->{$field},
            ];
        }

        $this->auditLogger->updated($configuration, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('redis_configuration.update', $configuration, $actor, $startedAt, $correlation);
        $this->runtimeConfigurator->applyForTenant($configuration->tenant_id, $configuration->brand_id, $correlation);

        return $configuration;
    }

    public function delete(RedisConfiguration $configuration, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $configuration->delete());

        $this->auditLogger->deleted($configuration, $actor, $startedAt, $correlation);
        $this->logPerformance('redis_configuration.delete', $configuration, $actor, $startedAt, $correlation);
        $this->runtimeConfigurator->applyForTenant($configuration->tenant_id, $configuration->brand_id, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?RedisConfiguration $configuration = null): array
    {
        $attributes = Arr::only($data, [
            'name',
            'slug',
            'brand_id',
            'cache_connection_name',
            'cache_host',
            'cache_port',
            'cache_database',
            'cache_tls',
            'cache_prefix',
            'session_connection_name',
            'session_host',
            'session_port',
            'session_database',
            'session_tls',
            'session_lifetime_minutes',
            'use_for_cache',
            'use_for_sessions',
            'is_active',
            'fallback_store',
            'options',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $configuration?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $configuration?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        if (empty($attributes['name'])) {
            $attributes['name'] = $configuration?->name ?? 'Redis Cluster '.Str::upper(Str::random(6));
        }

        if (empty($attributes['slug'])) {
            $source = $attributes['name'] ?? $configuration?->name ?? 'redis-configuration';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        $attributes['cache_connection_name'] = $attributes['cache_connection_name'] ?? $configuration?->cache_connection_name ?? 'cache';
        $attributes['session_connection_name'] = $attributes['session_connection_name'] ?? $configuration?->session_connection_name ?? $attributes['cache_connection_name'];
        $attributes['cache_port'] = isset($attributes['cache_port']) ? (int) $attributes['cache_port'] : ($configuration?->cache_port ?? 6379);
        $attributes['cache_database'] = isset($attributes['cache_database']) ? (int) $attributes['cache_database'] : ($configuration?->cache_database ?? 1);
        $attributes['session_port'] = isset($attributes['session_port']) ? (int) $attributes['session_port'] : ($configuration?->session_port ?? 6379);
        $attributes['session_database'] = isset($attributes['session_database']) ? (int) $attributes['session_database'] : ($configuration?->session_database ?? 0);
        $attributes['session_lifetime_minutes'] = isset($attributes['session_lifetime_minutes'])
            ? (int) $attributes['session_lifetime_minutes']
            : ($configuration?->session_lifetime_minutes ?? 120);
        $attributes['cache_tls'] = isset($attributes['cache_tls']) ? (bool) $attributes['cache_tls'] : ($configuration?->cache_tls ?? false);
        $attributes['session_tls'] = isset($attributes['session_tls']) ? (bool) $attributes['session_tls'] : ($configuration?->session_tls ?? false);
        $attributes['use_for_cache'] = isset($attributes['use_for_cache']) ? (bool) $attributes['use_for_cache'] : ($configuration?->use_for_cache ?? true);
        $attributes['use_for_sessions'] = isset($attributes['use_for_sessions']) ? (bool) $attributes['use_for_sessions'] : ($configuration?->use_for_sessions ?? true);
        $attributes['is_active'] = isset($attributes['is_active']) ? (bool) $attributes['is_active'] : ($configuration?->is_active ?? true);
        $attributes['fallback_store'] = in_array($attributes['fallback_store'] ?? '', ['file', 'array'], true)
            ? $attributes['fallback_store']
            : ($configuration?->fallback_store ?? 'file');

        if (array_key_exists('cache_auth_secret', $data)) {
            $attributes['cache_auth_secret'] = $this->encryptSecret($data['cache_auth_secret']);
        }

        if (array_key_exists('session_auth_secret', $data)) {
            $attributes['session_auth_secret'] = $this->encryptSecret($data['session_auth_secret']);
        }

        if (array_key_exists('options', $attributes) && $attributes['options'] !== null) {
            if (! is_array($attributes['options'])) {
                $attributes['options'] = (array) $attributes['options'];
            }
        } else {
            $attributes['options'] = $configuration?->options ?? [];
        }

        return $attributes;
    }

    protected function encryptSecret(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : encrypt($stringValue);
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        $value = $correlationId
            ?? request()?->headers->get('X-Correlation-ID')
            ?? request()?->header('X-Correlation-ID')
            ?? (string) Str::uuid();

        return Str::limit($value, 64, '');
    }

    protected function logPerformance(string $action, RedisConfiguration $configuration, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'redis_configuration_id' => $configuration->getKey(),
            'tenant_id' => $configuration->tenant_id,
            'brand_id' => $configuration->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'cache_host_digest' => $configuration->cacheHostDigest(),
            'session_host_digest' => $configuration->sessionHostDigest(),
        ]);
    }
}
