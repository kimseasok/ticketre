<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KbArticleSearchResource extends JsonResource
{
    public function toArray($request): array
    {
        $base = (new KbArticleResource($this->resource))->toArray($request);
        $metadata = $this->resource->scoutMetadata();

        return array_merge($base, [
            'score' => $metadata['score'] ?? null,
            'highlights' => $metadata['_formatted'] ?? null,
        ]);
    }
}
