<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TeamMembership
 */
class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'is_primary' => (bool) $this->is_primary,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->getKey(),
                    'name' => $this->user->name,
                ];
            }),
        ];
    }
}
