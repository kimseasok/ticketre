<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KbArticleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'category_id' => $this->resource->category_id,
            'author_id' => $this->resource->author_id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'locale' => $this->resource->locale,
            'status' => $this->resource->status,
            'content' => $this->resource->content,
            'excerpt' => $this->resource->excerpt,
            'metadata' => $this->resource->metadata,
            'published_at' => optional($this->resource->published_at)->toIso8601String(),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => KbCategoryResource::make($this->resource->category)),
            'author' => $this->whenLoaded('author', function () {
                $author = $this->resource->author;

                return [
                    'id' => $author?->getKey(),
                    'name' => $author?->name,
                ];
            }),
        ];
    }
}
