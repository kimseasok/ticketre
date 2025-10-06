<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'action' => $this->resource->action,
            'auditable_type' => Str::afterLast($this->resource->auditable_type, '\\'),
            'auditable_id' => $this->resource->auditable_id,
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'changes' => $this->resource->changes,
            'actor' => $this->when($this->resource->relationLoaded('user'), function () {
                return [
                    'id' => $this->resource->user?->getKey(),
                    'name' => $this->resource->user?->name,
                ];
            }),
            'created_at' => optional($this->resource->created_at)->toISOString(),
        ];
    }
}
