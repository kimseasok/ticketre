<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketWorkflowTransitionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'from' => $this->whenLoaded('fromState', fn () => $this->fromState?->slug, $this->from_state_id),
            'to' => $this->whenLoaded('toState', fn () => $this->toState?->slug, $this->to_state_id),
            'guard_hook' => $this->guard_hook,
            'requires_comment' => $this->requires_comment,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
