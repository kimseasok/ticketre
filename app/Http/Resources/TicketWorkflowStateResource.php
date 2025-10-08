<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketWorkflowStateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'is_initial' => $this->is_initial,
            'is_terminal' => $this->is_terminal,
            'sla_minutes' => $this->sla_minutes,
            'entry_hook' => $this->entry_hook,
            'description' => $this->description,
        ];
    }
}
