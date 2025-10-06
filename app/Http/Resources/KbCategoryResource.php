<?php

namespace App\Http\Resources;

use App\Models\KbCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class KbCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var KbCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->getKey(),
            'tenant_id' => $category->tenant_id,
            'brand_id' => $category->brand_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'order' => $category->order,
            'depth' => $category->depth,
            'path' => $category->path,
            'parent_id' => $category->parent_id,
            'parent' => $this->whenLoaded('parent', function () use ($category) {
                $parent = $category->parent;

                return [
                    'id' => $parent?->getKey(),
                    'name' => $parent?->name,
                    'slug' => $parent?->slug,
                ];
            }),
            'created_at' => optional($category->created_at)->toIso8601String(),
            'updated_at' => optional($category->updated_at)->toIso8601String(),
        ];
    }
}
