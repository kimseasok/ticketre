<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketEvent
 */
class TicketEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'correlation_id' => $this->correlation_id,
            'payload' => $this->payload,
            'broadcasted_at' => $this->broadcasted_at?->toIso8601String(),
            'ticket' => [
                'id' => $this->ticket_id,
                'subject' => $this->ticket?->subject,
                'status' => $this->ticket?->status,
                'priority' => $this->ticket?->priority,
                'workflow_state' => $this->ticket?->workflow_state,
                'assignee_id' => $this->ticket?->assignee_id,
            ],
            'initiator' => $this->when($this->initiator, fn () => [
                'id' => $this->initiator?->getKey(),
                'name' => $this->initiator?->name,
            ]),
        ];
    }
}
