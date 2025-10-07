<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BroadcastConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BroadcastConnectionFactory extends Factory
{
    protected $model = BroadcastConnection::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;
        $userId = $this->attributes['user_id'] ?? User::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
        ])->id;

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'user_id' => $userId,
            'connection_id' => sprintf('conn-%s', Str::uuid()),
            'channel_name' => sprintf('tenants.%d.brands.%d.tickets', $tenantId, $brandId),
            'status' => BroadcastConnection::STATUS_ACTIVE,
            'latency_ms' => $this->faker->numberBetween(5, 350),
            'last_seen_at' => now()->subSeconds($this->faker->numberBetween(0, 300)),
            'metadata' => [
                'client' => 'laravel-echo',
                'note' => 'NON-PRODUCTION seed',
            ],
            'correlation_id' => (string) Str::uuid(),
        ];
    }
}
