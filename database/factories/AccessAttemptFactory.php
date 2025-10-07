<?php

namespace Database\Factories;

use App\Models\AccessAttempt;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AccessAttemptFactory extends Factory
{
    protected $model = AccessAttempt::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'user_id' => null,
            'route' => 'api.tickets.index',
            'permission' => 'tickets.view',
            'granted' => false,
            'reason' => 'insufficient_permission',
            'correlation_id' => Str::uuid()->toString(),
            'ip_hash' => hash('sha256', $this->faker->ipv4()),
            'user_agent_hash' => hash('sha256', $this->faker->userAgent()),
            'metadata' => [
                'method' => 'GET',
                'path' => '/api/v1/tickets',
            ],
        ];
    }
}
