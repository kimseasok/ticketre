<?php

namespace App\Cache;

use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RedisFallbackStore implements Store
{
    use RetrievesMultipleKeys;

    protected bool $usingFallback = false;
    protected bool $loggedFallback = false;

    public function __construct(
        private readonly Store $primary,
        private readonly Store $fallback,
        private readonly string $fallbackName,
        private readonly string $connectionName,
        private readonly string $hostDigest,
    ) {
    }

    public function get($key): mixed
    {
        return $this->call(__FUNCTION__, [$key]);
    }

    public function many(array $keys): array
    {
        return $this->call(__FUNCTION__, [$keys]);
    }

    public function put($key, $value, $seconds): bool
    {
        return (bool) $this->call(__FUNCTION__, [$key, $value, $seconds]);
    }

    public function putMany(array $values, $seconds): bool
    {
        return (bool) $this->call(__FUNCTION__, [$values, $seconds]);
    }

    public function increment($key, $value = 1)
    {
        return $this->call(__FUNCTION__, [$key, $value]);
    }

    public function decrement($key, $value = 1)
    {
        return $this->call(__FUNCTION__, [$key, $value]);
    }

    public function forever($key, $value): bool
    {
        return (bool) $this->call(__FUNCTION__, [$key, $value]);
    }

    public function forget($key): bool
    {
        return (bool) $this->call(__FUNCTION__, [$key]);
    }

    public function flush(): bool
    {
        return (bool) $this->call(__FUNCTION__, []);
    }

    public function getPrefix(): string
    {
        return $this->usingFallback ? $this->fallback->getPrefix() : $this->primary->getPrefix();
    }

    public function usingFallback(): bool
    {
        return $this->usingFallback;
    }

    /**
     * @param  array<int|string, mixed>  $arguments
     */
    protected function call(string $method, array $arguments): mixed
    {
        if ($this->usingFallback) {
            return $this->fallback->{$method}(...$arguments);
        }

        try {
            return $this->primary->{$method}(...$arguments);
        } catch (Throwable $exception) {
            $this->activateFallback($exception);

            return $this->fallback->{$method}(...$arguments);
        }
    }

    protected function activateFallback(Throwable $exception): void
    {
        if ($this->usingFallback) {
            return;
        }

        $this->usingFallback = true;

        if ($this->loggedFallback) {
            return;
        }

        $this->loggedFallback = true;

        $request = request();
        $correlationId = $request?->headers->get('X-Correlation-ID')
            ?: ($request?->header('X-Correlation-ID'))
            ?: (string) Str::uuid();

        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;
        $brandId = app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null;

        Log::warning('redis.fallback.engaged', [
            'correlation_id' => $correlationId,
            'connection' => $this->connectionName,
            'fallback_store' => $this->fallbackName,
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'host_digest' => $this->hostDigest,
            'exception' => get_class($exception),
            'message_digest' => hash('sha256', (string) $exception->getMessage()),
        ]);
    }
}
