<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BroadcastConnection
 */
class BroadcastConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'broadcast-connections',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'user_id' => $this->user_id,
                'connection_id' => $this->connection_id,
                'channel_name' => $this->channel_name,
                'status' => $this->status,
                'latency_ms' => $this->latency_ms,
                'last_seen_at' => $this->last_seen_at?->toIso8601String(),
                'metadata' => $this->metadata ?? [],
                'correlation_id' => $this->correlation_id,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'user' => $this->when(
                    $this->relationLoaded('user') && $this->user,
                    fn () => [
                        'data' => [
                            'type' => 'users',
                            'id' => (string) $this->user->getKey(),
                            'attributes' => [
                                'name' => $this->user->name,
                                'email' => $this->user->email,
                            ],
                        ],
                    ]
                ),
                'brand' => $this->when(
                    $this->relationLoaded('brand') && $this->brand,
                    fn () => [
                        'data' => [
                            'type' => 'brands',
                            'id' => (string) $this->brand->getKey(),
                            'attributes' => [
                                'name' => $this->brand->name,
                                'slug' => $this->brand->slug,
                            ],
                        ],
                    ]
                ),
            ],
            'links' => [
                'self' => route('api.broadcast-connections.show', $this->resource),
            ],
        ];
    }
}
