<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketRelationshipFactory extends Factory
{
    protected $model = TicketRelationship::class;

    public function definition(): array
    {
        $primaryTicket = $this->attributes['primary_ticket_id'] ?? null;

        if ($primaryTicket instanceof Ticket) {
            $primary = $primaryTicket;
        } elseif ($primaryTicket) {
            $primary = Ticket::findOrFail($primaryTicket);
        } else {
            $primary = Ticket::factory()->create();
        }

        $relatedTicket = $this->attributes['related_ticket_id'] ?? null;

        if ($relatedTicket instanceof Ticket) {
            $related = $relatedTicket;
        } elseif ($relatedTicket) {
            $related = Ticket::findOrFail($relatedTicket);
        } else {
            $related = Ticket::factory()->create([
                'tenant_id' => $primary->tenant_id,
                'brand_id' => $primary->brand_id,
            ]);
        }

        $creator = $this->attributes['created_by_id'] ?? User::factory()->create([
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
        ])->getKey();

        return [
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'primary_ticket_id' => $primary->getKey(),
            'related_ticket_id' => $related->getKey(),
            'relationship_type' => $this->faker->randomElement(TicketRelationship::allowedTypes()),
            'context' => [
                'notes' => $this->faker->sentence(),
            ],
            'created_by_id' => $creator,
            'updated_by_id' => null,
        ];
    }
}
