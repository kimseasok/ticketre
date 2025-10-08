<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRelationship>
 */
class TicketRelationshipFactory extends Factory
{
    protected $model = TicketRelationship::class;

    public function definition(): array
    {
        $primary = Ticket::factory()->create();
        $related = Ticket::factory()->create([
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
        ]);

        return [
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'primary_ticket_id' => $primary->getKey(),
            'related_ticket_id' => $related->getKey(),
            'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
            'created_by' => User::factory()->create([
                'tenant_id' => $primary->tenant_id,
                'brand_id' => $primary->brand_id,
            ])->getKey(),
            'context' => [
                'note' => 'Factory generated relationship for testing.',
            ],
            'correlation_id' => $this->faker->uuid(),
        ];
    }
}
