<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SlaPolicy
 */
class SlaPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'timezone' => $this->resource->timezone,
            'business_hours' => $this->resource->business_hours ?? [],
            'holiday_exceptions' => $this->resource->holiday_exceptions ?? [],
            'default_first_response_minutes' => $this->resource->default_first_response_minutes,
            'default_resolution_minutes' => $this->resource->default_resolution_minutes,
            'enforce_business_hours' => (bool) $this->resource->enforce_business_hours,
            'targets' => $this->whenLoaded('targets', function () {
                return $this->resource->targets->map(fn ($target) => [
                    'id' => $target->getKey(),
                    'channel' => $target->channel,
                    'priority' => $target->priority,
                    'first_response_minutes' => $target->first_response_minutes,
                    'resolution_minutes' => $target->resolution_minutes,
                    'use_business_hours' => (bool) $target->use_business_hours,
                ])->values()->all();
            }),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }
}
