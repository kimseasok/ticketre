<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TeamMembership
 */
class TeamMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'team-memberships',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'team_id' => $this->resource->team_id,
                'user_id' => $this->resource->user_id,
                'role' => $this->resource->role,
                'is_primary' => $this->resource->is_primary,
                'joined_at' => $this->resource->joined_at?->toAtomString(),
                'created_at' => $this->resource->created_at?->toAtomString(),
                'updated_at' => $this->resource->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'user' => $this->whenLoaded('user', fn () => [
                    'data' => [
                        'type' => 'users',
                        'id' => (string) $this->resource->user?->getKey(),
                        'attributes' => [
                            'name' => $this->resource->user?->name,
                            'email' => $this->resource->user?->email,
                        ],
                    ],
                ]),
            ],
        ];
    }
}
