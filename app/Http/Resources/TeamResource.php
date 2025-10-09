<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'teams',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'slug' => $this->resource->slug,
                'default_queue' => $this->resource->default_queue,
                'description' => $this->resource->description,
                'brand_id' => $this->resource->brand_id,
                'tenant_id' => $this->resource->tenant_id,
                'memberships_count' => $this->when(isset($this->resource->memberships_count), $this->resource->memberships_count),
                'created_at' => $this->resource->created_at?->toAtomString(),
                'updated_at' => $this->resource->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'memberships' => TeamMembershipResource::collection($this->whenLoaded('memberships')),
            ],
        ];
    }
}
