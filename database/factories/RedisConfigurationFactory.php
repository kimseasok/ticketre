<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\RedisConfiguration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\RedisConfiguration>
 */
class RedisConfigurationFactory extends Factory
{
    protected $model = RedisConfiguration::class;

    public function definition(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->first() ?? Tenant::factory()->create();
        $brand = Brand::query()->where('tenant_id', $tenant->getKey())->first();

        return [
            'tenant_id' => $tenant->getKey(),
            'brand_id' => $brand?->getKey(),
            'name' => $this->faker->unique()->words(3, true).' Redis Cluster',
            'slug' => Str::slug($this->faker->unique()->words(2, true).'-redis'),
            'cache_connection_name' => 'cache',
            'cache_host' => $this->faker->ipv4(),
            'cache_port' => 6379,
            'cache_database' => 1,
            'cache_tls' => false,
            'cache_prefix' => 'tenant_'.$tenant->slug.'_cache',
            'session_connection_name' => 'default',
            'session_host' => $this->faker->ipv4(),
            'session_port' => 6379,
            'session_database' => 0,
            'session_tls' => false,
            'session_lifetime_minutes' => 120,
            'use_for_cache' => true,
            'use_for_sessions' => true,
            'is_active' => true,
            'fallback_store' => 'file',
            'cache_auth_secret' => encrypt('NON-PRODUCTION:cache-secret'),
            'session_auth_secret' => encrypt('NON-PRODUCTION:session-secret'),
            'options' => [
                'cluster' => 'redis',
            ],
        ];
    }
}
