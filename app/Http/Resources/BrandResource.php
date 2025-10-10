<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'brands',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'domain' => $this->domain,
                'domain_digest' => $this->domain ? hash('sha256', (string) $this->domain) : null,
                'theme' => $this->theme,
                'theme_preview' => $this->theme_preview,
                'theme_settings' => $this->theme_settings,
                'primary_logo_path' => $this->primary_logo_path,
                'secondary_logo_path' => $this->secondary_logo_path,
                'favicon_path' => $this->favicon_path,
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'tenant' => [
                    'data' => $this->when($this->relationLoaded('tenant'), function () {
                        return [
                            'type' => 'tenants',
                            'id' => (string) $this->tenant?->getKey(),
                            'attributes' => [
                                'name' => $this->tenant?->name,
                                'slug' => $this->tenant?->slug,
                            ],
                        ];
                    }),
                ],
                'domains' => [
                    'data' => BrandDomainResource::collection($this->whenLoaded('domains')),
                ],
            ],
            'links' => [
                'self' => route('api.brands.show', $this->resource),
            ],
        ];
    }
}
