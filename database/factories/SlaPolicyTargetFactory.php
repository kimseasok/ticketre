<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\SlaPolicyTarget;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicyTarget>
 */
class SlaPolicyTargetFactory extends Factory
{
    protected $model = SlaPolicyTarget::class;

    public function definition(): array
    {
        $policyId = $this->attributes['sla_policy_id'] ?? SlaPolicy::factory()->create()->id;

        $channels = [
            Ticket::CHANNEL_AGENT,
            Ticket::CHANNEL_PORTAL,
            Ticket::CHANNEL_EMAIL,
            Ticket::CHANNEL_CHAT,
            Ticket::CHANNEL_API,
        ];

        $priorities = ['low', 'normal', 'high', 'urgent'];

        return [
            'sla_policy_id' => $policyId,
            'channel' => $this->faker->randomElement($channels),
            'priority' => $this->faker->randomElement($priorities),
            'first_response_minutes' => $this->faker->numberBetween(5, 180),
            'resolution_minutes' => $this->faker->numberBetween(60, 2880),
            'use_business_hours' => $this->faker->boolean(80),
        ];
    }
}
