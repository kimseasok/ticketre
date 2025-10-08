<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketWorkflowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type' => 'ticket-workflows',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'is_default' => (bool) $this->is_default,
                'states' => TicketWorkflowStateResource::collection($this->whenLoaded('states')),
                'transitions' => TicketWorkflowTransitionResource::collection($this->whenLoaded('transitions')),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
        ];
    }
}
