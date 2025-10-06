<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function __construct(private readonly TicketLifecycleBroadcaster $broadcaster)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Ticket
    {
        $ticket = Ticket::create($data);

        $this->broadcaster->record($ticket->refresh(), TicketEvent::TYPE_CREATED, [
            'changes' => $data,
        ], $actor);

        return $ticket->fresh(['assignee']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $ticket->fill($data);
        $changes = Arr::except($ticket->getDirty(), ['updated_at']);
        $ticket->save();

        if (! empty($changes)) {
            $this->broadcaster->record($ticket->fresh(['assignee']), TicketEvent::TYPE_UPDATED, [
                'changes' => $changes,
            ], $actor);

            if (array_key_exists('assignee_id', $changes)) {
                $this->broadcaster->record($ticket->fresh(['assignee']), TicketEvent::TYPE_ASSIGNED, [
                    'assignee_id' => $ticket->assignee_id,
                ], $actor);
            }
        }

        return $ticket->fresh(['assignee']);
    }

    public function assign(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        $ticket->assignee()->associate($assignee);
        $ticket->save();

        $this->broadcaster->record($ticket->fresh(['assignee']), TicketEvent::TYPE_ASSIGNED, [
            'assignee_id' => $ticket->assignee_id,
        ], $actor);

        return $ticket->fresh(['assignee']);
    }

    public function merge(Ticket $primary, Ticket $secondary, User $actor): Ticket
    {
        $this->broadcaster->record($primary->fresh(), TicketEvent::TYPE_MERGED, [
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
        ], $actor);

        Log::channel(config('logging.default'))->info('ticket.lifecycle.merged', [
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'initiator_id' => $actor->getKey(),
            'context' => 'ticket_lifecycle',
        ]);

        return $primary->fresh();
    }
}
