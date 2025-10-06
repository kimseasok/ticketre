<?php

namespace App\Jobs;

use App\Broadcasting\Events\TicketLifecycleBroadcast;
use App\Models\TicketEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastTicketEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $ticketEventId)
    {
        $this->onQueue('broadcasts');
    }

    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(): void
    {
        $event = TicketEvent::query()->with(['ticket', 'initiator'])->find($this->ticketEventId);

        if (! $event) {
            Log::channel(config('logging.default'))->warning('ticket.lifecycle.missing_event', [
                'ticket_event_id' => $this->ticketEventId,
                'context' => 'ticket_lifecycle',
            ]);

            return;
        }

        Event::dispatch(new TicketLifecycleBroadcast($event));

        Log::channel(config('logging.default'))->info('ticket.lifecycle.broadcasted', [
            'ticket_event_id' => $event->getKey(),
            'ticket_id' => $event->ticket_id,
            'tenant_id' => $event->tenant_id,
            'brand_id' => $event->brand_id,
            'type' => $event->type,
            'visibility' => $event->visibility,
            'correlation_id' => $event->correlation_id,
            'context' => 'ticket_lifecycle',
        ]);
    }

    public function failed(Throwable $throwable): void
    {
        $event = TicketEvent::find($this->ticketEventId);

        Log::channel(config('logging.default'))->error('ticket.lifecycle.broadcast_failed', [
            'ticket_event_id' => $this->ticketEventId,
            'error' => $throwable->getMessage(),
            'correlation_id' => $event?->correlation_id,
            'context' => 'ticket_lifecycle',
        ]);
    }
}
