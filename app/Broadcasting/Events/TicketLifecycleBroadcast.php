<?php

namespace App\Broadcasting\Events;

use App\Http\Resources\TicketEventResource;
use App\Models\TicketEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TicketLifecycleBroadcast implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public TicketEvent $event)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('tenants.%d.brands.%d.tickets', $this->event->tenant_id, $this->event->brand_id ?? 0)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.lifecycle';
    }

    public function broadcastWith(): array
    {
        return TicketEventResource::make($this->event->loadMissing(['ticket', 'initiator']))->resolve();
    }
}
