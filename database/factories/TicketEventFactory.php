<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
{
    protected $model = TicketEvent::class;

    public function definition(): array
    {
        $ticket = Ticket::factory()->create();

        return [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'initiator_id' => User::factory()->create([
                'tenant_id' => $ticket->tenant_id,
                'brand_id' => $ticket->brand_id,
            ])->getKey(),
            'type' => TicketEvent::TYPE_CREATED,
            'visibility' => TicketEvent::VISIBILITY_INTERNAL,
            'correlation_id' => Str::uuid()->toString(),
            'payload' => [
                'event' => TicketEvent::TYPE_CREATED,
                'ticket' => [
                    'id' => $ticket->getKey(),
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                ],
            ],
            'broadcasted_at' => now(),
        ];
    }

    public function forTicket(Ticket $ticket): self
    {
        return $this->state(fn () => [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'initiator_id' => User::factory()->create([
                'tenant_id' => $ticket->tenant_id,
                'brand_id' => $ticket->brand_id,
            ])->getKey(),
            'payload' => [
                'event' => TicketEvent::TYPE_CREATED,
                'ticket' => [
                    'id' => $ticket->getKey(),
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                ],
            ],
        ]);
    }
}
