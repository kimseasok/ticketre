<?php

namespace App\Services;

use App\Jobs\BroadcastTicketEventJob;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketLifecycleBroadcaster
{
    public function record(Ticket $ticket, string $type, array $payload, ?User $initiator = null, string $visibility = TicketEvent::VISIBILITY_INTERNAL, bool $shouldQueue = true): TicketEvent
    {
        $startedAt = microtime(true);

        $correlationId = $this->resolveCorrelationId();

        $event = TicketEvent::create([
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'initiator_id' => $initiator?->getKey(),
            'type' => $type,
            'visibility' => $visibility,
            'correlation_id' => $correlationId,
            'payload' => $this->normalizePayload($ticket, $payload, $type, $initiator, $visibility),
            'broadcasted_at' => now(),
        ]);

        if ($shouldQueue) {
            BroadcastTicketEventJob::dispatch($event->getKey());
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info('ticket.lifecycle.recorded', [
            'ticket_event_id' => $event->getKey(),
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'type' => $type,
            'visibility' => $visibility,
            'initiator_id' => $initiator?->getKey(),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'subject_digest' => hash('sha256', (string) $ticket->subject),
            'context' => 'ticket_lifecycle',
        ]);

        return $event;
    }

    protected function resolveCorrelationId(): string
    {
        $header = request()?->header('X-Correlation-ID');

        return $header ? Str::limit($header, 64, '') : (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(Ticket $ticket, array $payload, string $type, ?User $initiator, string $visibility): array
    {
        return array_merge([
            'event' => $type,
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'visibility' => $visibility,
            'ticket' => [
                'id' => $ticket->getKey(),
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'workflow_state' => $ticket->workflow_state,
                'assignee_id' => $ticket->assignee_id,
            ],
            'initiator_id' => $initiator?->getKey(),
        ], $payload);
    }
}
