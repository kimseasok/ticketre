<?php

namespace App\Http\Resources;

use App\Models\KbArticle;
use Illuminate\Http\Resources\Json\JsonResource;

class KbArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var KbArticle $article */
        $article = $this->resource;

        return [
            'id' => $article->getKey(),
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'category_id' => $article->category_id,
            'author_id' => $article->author_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'locale' => $article->locale,
            'status' => $article->status,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'metadata' => $article->metadata,
            'published_at' => optional($article->published_at)->toIso8601String(),
            'created_at' => optional($article->created_at)->toIso8601String(),
            'updated_at' => optional($article->updated_at)->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => KbCategoryResource::make($article->category)),
            'author' => $this->whenLoaded('author', function () use ($article) {
                $author = $article->author;

                return [
                    'id' => $author?->getKey(),
                    'name' => $author?->name,
                ];
            }),
        ];
    }
}
