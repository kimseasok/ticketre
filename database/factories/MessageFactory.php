<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $ticketId = $this->attributes['ticket_id'] ?? Ticket::factory()->create()->id;
        $ticket = Ticket::query()->findOrFail($ticketId);

        $userId = $this->attributes['user_id'] ?? $ticket->assignee_id;
        $user = $userId ? User::query()->find($userId) : $ticket->assignee;

        if ($user && ! $user->hasAnyRole(['Admin', 'Agent'])) {
            $user->assignRole('Agent');
        }

        $visibility = $this->attributes['visibility'] ?? $this->faker->randomElement([
            Message::VISIBILITY_PUBLIC,
            Message::VISIBILITY_INTERNAL,
        ]);

        return [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticketId,
            'user_id' => $user?->id,
            'author_role' => $user?->getRoleNames()->first(),
            'visibility' => $visibility,
            'body' => $this->faker->paragraph(),
            'sent_at' => now(),
        ];
    }
}
