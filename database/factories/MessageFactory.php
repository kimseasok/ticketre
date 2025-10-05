<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $ticketId = $this->attributes['ticket_id'] ?? Ticket::factory()->create()->id;
        $ticket = Ticket::query()->findOrFail($ticketId);

        return [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticketId,
            'user_id' => $ticket->assignee_id,
            'visibility' => $this->faker->randomElement(['public', 'internal']),
            'body' => $this->faker->paragraph(),
            'sent_at' => now(),
        ];
    }
}
