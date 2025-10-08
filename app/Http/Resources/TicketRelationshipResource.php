<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketRelationship
 */
class TicketRelationshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'ticket_relationships',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'relationship_type' => $this->relationship_type,
                'context' => $this->context ?? [],
                'correlation_id' => $this->correlation_id,
                'created_by' => $this->created_by,
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
                'related_ticket' => $this->when(
                    $this->relationLoaded('relatedTicket') && $this->relatedTicket,
                    fn () => [
                        'data' => [
                            'type' => 'tickets',
                            'id' => (string) $this->relatedTicket->getKey(),
                            'attributes' => [
                                'subject' => $this->relatedTicket->subject,
                                'status' => $this->relatedTicket->status,
                            ],
                        ],
                    ]
                ),
                'creator' => $this->when(
                    $this->relationLoaded('creator') && $this->creator,
                    fn () => [
                        'data' => [
                            'type' => 'users',
                            'id' => (string) $this->creator->getKey(),
                            'attributes' => [
                                'name' => $this->creator->name,
                                'email' => $this->creator->email,
                            ],
                        ],
                    ]
                ),
            ],
            'links' => [
                'self' => route('api.ticket-relationships.show', $this->resource),
            ],
        ];
    }
}
