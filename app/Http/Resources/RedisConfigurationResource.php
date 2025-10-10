<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RedisConfiguration */
class RedisConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'redis-configurations',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'name' => $this->name,
                'slug' => $this->slug,
                'cache_connection_name' => $this->cache_connection_name,
                'cache_host_digest' => $this->cacheHostDigest(),
                'cache_port' => $this->cache_port,
                'cache_database' => $this->cache_database,
                'cache_tls' => $this->cache_tls,
                'cache_prefix' => $this->cache_prefix,
                'session_connection_name' => $this->session_connection_name,
                'session_host_digest' => $this->sessionHostDigest(),
                'session_port' => $this->session_port,
                'session_database' => $this->session_database,
                'session_tls' => $this->session_tls,
                'session_lifetime_minutes' => $this->session_lifetime_minutes,
                'use_for_cache' => $this->use_for_cache,
                'use_for_sessions' => $this->use_for_sessions,
                'is_active' => $this->is_active,
                'fallback_store' => $this->fallback_store,
                'options' => $this->options ?? [],
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'tenant' => [
                    'data' => $this->when($this->relationLoaded('tenant'), function () {
                        return [
                            'type' => 'tenants',
                            'id' => (string) $this->tenant?->getKey(),
                            'attributes' => [
                                'name' => $this->tenant?->name,
                                'slug' => $this->tenant?->slug,
                            ],
                        ];
                    }),
                ],
                'brand' => [
                    'data' => $this->when($this->relationLoaded('brand') && $this->brand, function () {
                        return [
                            'type' => 'brands',
                            'id' => (string) $this->brand->getKey(),
                            'attributes' => [
                                'name' => $this->brand->name,
                                'slug' => $this->brand->slug,
                            ],
                        ];
                    }),
                ],
            ],
            'links' => [
                'self' => route('api.redis-configurations.show', $this->resource),
            ],
        ];
    }
}
