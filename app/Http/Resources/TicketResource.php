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
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'brand_id' => $this->brand_id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'department' => $this->department,
            'category' => $this->category,
            'workflow_state' => $this->workflow_state,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'assignee' => $this->when($this->assignee, fn () => [
                'id' => $this->assignee?->getKey(),
                'name' => $this->assignee?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
