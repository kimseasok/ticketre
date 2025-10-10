<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BrandAsset */
class BrandAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'brand-assets',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'brand_id' => $this->brand_id,
                'type' => $this->type,
                'disk' => $this->disk,
                'path' => $this->path,
                'path_digest' => $this->pathDigest(),
                'version' => $this->version,
                'content_type' => $this->content_type,
                'size' => $this->size,
                'checksum' => $this->checksum,
                'cache_control' => $this->cache_control,
                'cdn_url' => $this->cdn_url,
                'meta' => $this->meta ?? [],
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'brand' => [
                    'data' => $this->when($this->relationLoaded('brand'), function () {
                        return [
                            'type' => 'brands',
                            'id' => (string) $this->brand?->getKey(),
                            'attributes' => [
                                'name' => $this->brand?->name,
                                'slug' => $this->brand?->slug,
                            ],
                        ];
                    }),
                ],
            ],
            'links' => [
                'self' => route('api.brand-assets.show', $this->resource),
                'deliver' => route('api.brand-assets.deliver', $this->resource),
            ],
        ];
    }
}
