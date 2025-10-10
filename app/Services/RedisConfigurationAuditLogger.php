<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RedisConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RedisConfigurationAuditLogger
{
    public function created(RedisConfiguration $configuration, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = ['snapshot' => $this->snapshot($configuration)];

        $this->persist($configuration, $actor, 'redis.configuration.created', $payload, $correlationId);
        $this->logEvent('redis.configuration.created', $configuration, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(RedisConfiguration $configuration, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($configuration, $actor, 'redis.configuration.updated', $changes, $correlationId);
        $this->logEvent('redis.configuration.updated', $configuration, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(RedisConfiguration $configuration, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = ['snapshot' => $this->snapshot($configuration)];

        $this->persist($configuration, $actor, 'redis.configuration.deleted', $payload, $correlationId);
        $this->logEvent('redis.configuration.deleted', $configuration, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(RedisConfiguration $configuration): array
    {
        return [
            'name' => $configuration->name,
            'slug' => $configuration->slug,
            'cache_connection_name' => $configuration->cache_connection_name,
            'cache_host_digest' => $configuration->cacheHostDigest(),
            'cache_port' => $configuration->cache_port,
            'cache_database' => $configuration->cache_database,
            'cache_tls' => $configuration->cache_tls,
            'session_connection_name' => $configuration->session_connection_name,
            'session_host_digest' => $configuration->sessionHostDigest(),
            'session_port' => $configuration->session_port,
            'session_database' => $configuration->session_database,
            'session_tls' => $configuration->session_tls,
            'session_lifetime_minutes' => $configuration->session_lifetime_minutes,
            'use_for_cache' => $configuration->use_for_cache,
            'use_for_sessions' => $configuration->use_for_sessions,
            'fallback_store' => $configuration->fallback_store,
            'is_active' => $configuration->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(RedisConfiguration $configuration, User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $configuration->tenant_id,
            'brand_id' => $configuration->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => RedisConfiguration::class,
            'auditable_id' => $configuration->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, RedisConfiguration $configuration, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'redis_configuration_id' => $configuration->getKey(),
            'tenant_id' => $configuration->tenant_id,
            'brand_id' => $configuration->brand_id,
            'cache_host_digest' => $configuration->cacheHostDigest(),
            'session_host_digest' => $configuration->sessionHostDigest(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'redis_configuration_audit',
            'changes_keys' => array_keys($payload),
        ]);
    }
}
