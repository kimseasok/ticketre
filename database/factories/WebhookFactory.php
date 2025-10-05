<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;

        return [
            'tenant_id' => $tenantId,
            'name' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
            'secret' => $this->faker->sha256(),
            'events' => ['ticket.created'],
            'status' => 'active',
            'last_invoked_at' => now(),
        ];
    }
}
