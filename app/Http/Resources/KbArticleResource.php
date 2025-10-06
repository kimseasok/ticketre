<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KbArticleResource extends JsonResource
{
    public function toArray($request): array
    {
        $requestedLocale = $request?->query('locale');
        $translation = $this->resource->translationForLocale($requestedLocale);

        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'category_id' => $this->resource->category_id,
            'author_id' => $this->resource->author_id,
            'slug' => $this->resource->slug,
            'default_locale' => $this->resource->default_locale,
            'locale' => $translation?->locale,
            'title' => $translation?->title,
            'status' => $translation?->status,
            'content' => $translation?->content,
            'excerpt' => $translation?->excerpt,
            'metadata' => $translation?->metadata,
            'published_at' => optional($translation?->published_at)->toIso8601String(),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
            'translations' => $this->resource->translations
                ->map(function ($translation) {
                    return [
                        'id' => $translation->getKey(),
                        'locale' => $translation->locale,
                        'title' => $translation->title,
                        'status' => $translation->status,
                        'content' => $translation->content,
                        'excerpt' => $translation->excerpt,
                        'metadata' => $translation->metadata,
                        'published_at' => optional($translation->published_at)->toIso8601String(),
                    ];
                })
                ->values(),
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
