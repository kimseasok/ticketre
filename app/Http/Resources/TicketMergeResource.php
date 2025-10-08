<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketMerge
 */
class TicketMergeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'ticket_merges',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'status' => $this->status,
                'summary' => $this->summary ?? [],
                'correlation_id' => $this->correlation_id,
                'completed_at' => $this->completed_at?->toIso8601String(),
                'failed_at' => $this->failed_at?->toIso8601String(),
                'failure_reason' => $this->failure_reason,
                'primary_ticket_id' => $this->primary_ticket_id,
                'secondary_ticket_id' => $this->secondary_ticket_id,
                'initiated_by' => $this->initiated_by,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'primary_ticket' => $this->when(
                    $this->relationLoaded('primaryTicket') && $this->primaryTicket,
                    fn () => [
                        'data' => [
                            'type' => 'tickets',
                            'id' => (string) $this->primaryTicket->getKey(),
                            'attributes' => [
                                'subject' => $this->primaryTicket->subject,
                                'status' => $this->primaryTicket->status,
                            ],
                        ],
                    ]
                ),
                'secondary_ticket' => $this->when(
                    $this->relationLoaded('secondaryTicket') && $this->secondaryTicket,
                    fn () => [
                        'data' => [
                            'type' => 'tickets',
                            'id' => (string) $this->secondaryTicket->getKey(),
                            'attributes' => [
                                'subject' => $this->secondaryTicket->subject,
                                'status' => $this->secondaryTicket->status,
                            ],
                        ],
                    ]
                ),
                'initiator' => $this->when(
                    $this->relationLoaded('initiator') && $this->initiator,
                    fn () => [
                        'data' => [
                            'type' => 'users',
                            'id' => (string) $this->initiator->getKey(),
                            'attributes' => [
                                'name' => $this->initiator->name,
                                'email' => $this->initiator->email,
                            ],
                        ],
                    ]
                ),
            ],
            'links' => [
                'self' => route('api.ticket-merges.show', $this->resource),
            ],
        ];
    }
}
