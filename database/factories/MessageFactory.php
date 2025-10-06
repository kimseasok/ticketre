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
        $ticket = Ticket::factory()->create();

        return [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->assignee_id,
            'author_role' => $this->faker->randomElement([
                Message::ROLE_AGENT,
                Message::ROLE_CONTACT,
            ]),
            'visibility' => $this->faker->randomElement([
                Message::VISIBILITY_PUBLIC,
                Message::VISIBILITY_INTERNAL,
            ]),
            'body' => $this->faker->paragraph(),
            'sent_at' => now(),
        ];
    }
}
