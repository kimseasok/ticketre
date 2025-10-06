<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function __construct(
        private readonly TicketLifecycleBroadcaster $broadcaster,
        private readonly TicketAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Ticket
    {
        $startedAt = microtime(true);

        $ticket = Ticket::create($data);
        $ticket->refresh();

        $this->auditLogger->created($ticket, $actor, $startedAt);

        $this->broadcaster->record($ticket, TicketEvent::TYPE_CREATED, [
            'changes' => $data,
        ], $actor);

        return $ticket->fresh(['assignee']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $startedAt = microtime(true);

        $ticket->fill($data);
        $dirty = Arr::except($ticket->getDirty(), ['updated_at']);
        $original = Arr::only($ticket->getOriginal(), array_keys($dirty));
        $ticket->save();

        $ticket = $ticket->fresh(['assignee']);

        if (! empty($dirty)) {
            $this->auditLogger->updated($ticket, $actor, $dirty, $original, $startedAt);

            $this->broadcaster->record($ticket, TicketEvent::TYPE_UPDATED, [
                'changes' => $dirty,
            ], $actor);

            if (array_key_exists('assignee_id', $dirty)) {
                $this->broadcaster->record($ticket, TicketEvent::TYPE_ASSIGNED, [
                    'assignee_id' => $ticket->assignee_id,
                ], $actor);
            }
        }

        return $ticket;
    }

    public function assign(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        $startedAt = microtime(true);
        $original = ['assignee_id' => $ticket->assignee_id];

        $ticket->assignee()->associate($assignee);
        $ticket->save();

        $ticket = $ticket->fresh(['assignee']);

        $this->auditLogger->updated($ticket, $actor, ['assignee_id' => $ticket->assignee_id], $original, $startedAt);

        $this->broadcaster->record($ticket, TicketEvent::TYPE_ASSIGNED, [
            'assignee_id' => $ticket->assignee_id,
        ], $actor);

        return $ticket;
    }

    public function delete(Ticket $ticket, User $actor): void
    {
        $startedAt = microtime(true);

        $ticket->delete();

        $this->auditLogger->deleted($ticket, $actor, $startedAt);
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
