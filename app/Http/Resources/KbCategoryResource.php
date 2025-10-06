<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KbCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'order' => $this->resource->order,
            'depth' => $this->resource->depth,
            'path' => $this->resource->path,
            'parent_id' => $this->resource->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                $parent = $this->resource->parent;

                return [
                    'id' => $parent?->getKey(),
                    'name' => $parent?->name,
                    'slug' => $parent?->slug,
                ];
            }),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }
}
