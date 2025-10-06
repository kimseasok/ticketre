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
            'id' => $this->id,
            'primary_ticket_id' => $this->primary_ticket_id,
            'related_ticket_id' => $this->related_ticket_id,
            'relationship_type' => $this->relationship_type,
            'context' => $this->context,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'primary_ticket' => $this->whenLoaded('primaryTicket', function () {
                return [
                    'id' => $this->primaryTicket?->getKey(),
                    'subject' => $this->primaryTicket?->subject,
                    'status' => $this->primaryTicket?->status,
                ];
            }),
            'related_ticket' => $this->whenLoaded('relatedTicket', function () {
                return [
                    'id' => $this->relatedTicket?->getKey(),
                    'subject' => $this->relatedTicket?->subject,
                    'status' => $this->relatedTicket?->status,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
