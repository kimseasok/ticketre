<?php

namespace App\Services;

use App\Exceptions\InvalidCustomFieldException;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Support\TicketCustomFieldValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
    public function create(array $data, User $actor, ?string $correlationId = null): Ticket
    {
        $startedAt = microtime(true);

        $data = $this->preparePayload($data);
        $data['channel'] = $data['channel'] ?? Ticket::CHANNEL_AGENT;

        $ticket = Ticket::create($data);
        $ticket->refresh();

        $this->auditLogger->created($ticket, $actor, $startedAt);

        $this->broadcaster->record($ticket, TicketEvent::TYPE_CREATED, [
            'changes' => $data,
        ], $actor);

        if ($data['channel'] === Ticket::CHANNEL_API) {
            Log::channel(config('logging.default'))->info('ticket.api.created', [
                'ticket_id' => $ticket->getKey(),
                'tenant_id' => $ticket->tenant_id,
                'brand_id' => $ticket->brand_id,
                'assignee_id' => $ticket->assignee_id,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'channel' => $ticket->channel,
                'custom_fields_count' => count($ticket->custom_fields ?? []),
                'correlation_id' => $correlationId,
                'subject_hash' => hash('sha256', mb_strtolower((string) $ticket->subject)),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'context' => 'api_ticket_create',
            ]);
        }

        return $ticket->fresh(['assignee', 'contact', 'company']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $startedAt = microtime(true);

        $data = $this->preparePayload($data, allowMissing: true);

        $ticket->fill($data);
        $dirty = Arr::except($ticket->getDirty(), ['updated_at']);
        $original = Arr::only($ticket->getOriginal(), array_keys($dirty));
        $ticket->save();

        $ticket = $ticket->fresh(['assignee', 'contact', 'company']);

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preparePayload(array $data, bool $allowMissing = false): array
    {
        if (array_key_exists('custom_fields', $data) || ! $allowMissing) {
            $fields = $data['custom_fields'] ?? [];

            if (! is_array($fields)) {
                $fields = [];
            }

            try {
                $data['custom_fields'] = TicketCustomFieldValidator::validate($fields);
            } catch (InvalidCustomFieldException $exception) {
                throw ValidationException::withMessages($exception->errors());
            }
        }

        if (array_key_exists('metadata', $data) || ! $allowMissing) {
            $metadata = $data['metadata'] ?? [];

            if (! is_array($metadata)) {
                $metadata = [];
            }

            $data['metadata'] = $this->sanitizeMetadata($metadata);
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<mixed>
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->sanitizeMetadata($value);
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                return $value;
            }

            if (is_string($value)) {
                return mb_substr($value, 0, 1000);
            }

            return (string) $value;
        }, $metadata);
    }
}
