<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'tickets',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'subject' => $this->subject,
                'status' => $this->status,
                'priority' => $this->priority,
                'channel' => $this->channel,
                'department' => $this->department,
                'category' => $this->category,
                'workflow_state' => $this->workflow_state,
                'metadata' => $this->metadata ?? [],
                'custom_fields' => $this->custom_fields ?? [],
                'sla_due_at' => $this->sla_due_at?->toIso8601String(),
                'sla_policy_id' => $this->sla_policy_id,
                'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
                'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'assignee' => $this->when(
                    $this->relationLoaded('assignee') && $this->assignee,
                    fn () => [
                        'data' => [
                            'type' => 'users',
                            'id' => (string) $this->assignee->getKey(),
                            'attributes' => [
                                'name' => $this->assignee->name,
                                'email' => $this->assignee->email,
                            ],
                        ],
                    ]
                ),
                'contact' => $this->when(
                    $this->relationLoaded('contact') && $this->contact,
                    fn () => [
                        'data' => [
                            'type' => 'contacts',
                            'id' => (string) $this->contact->getKey(),
                            'attributes' => [
                                'name' => $this->contact->name,
                                'email' => $this->contact->email,
                            ],
                        ],
                    ]
                ),
                'company' => $this->when(
                    $this->relationLoaded('company') && $this->company,
                    fn () => [
                        'data' => [
                            'type' => 'companies',
                            'id' => (string) $this->company->getKey(),
                            'attributes' => [
                                'name' => $this->company->name,
                            ],
                        ],
                    ]
                ),
            ],
            'links' => [
                'self' => route('api.tickets.show', $this->resource),
                'messages' => route('api.tickets.messages.index', ['ticket' => $this->resource]),
                'events' => route('api.tickets.events.index', ['ticket' => $this->resource]),
            ],
        ];
    }
}
