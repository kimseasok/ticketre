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
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'channel' => $this->channel,
            'workflow_state' => $this->workflow_state,
            'department' => $this->whenLoaded('departmentRelation', function () {
                if (! $this->departmentRelation) {
                    return null;
                }

                return [
                    'id' => $this->departmentRelation->getKey(),
                    'name' => $this->departmentRelation->name,
                ];
            }),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn ($category) => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->all();
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                ])->all();
            }),
            'category' => $this->category,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'metadata' => (object) ($this->metadata ?? []),
            'assignee' => $this->when($this->assignee, fn () => [
                'id' => $this->assignee?->getKey(),
                'name' => $this->assignee?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
