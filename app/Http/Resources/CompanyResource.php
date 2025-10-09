<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'companies',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'domain' => $this->resource->domain,
                'tenant_id' => $this->resource->tenant_id,
                'brand_id' => $this->resource->brand_id,
                'tags' => $this->resource->tags ?? [],
                'metadata' => $this->resource->metadata ?? [],
                'contacts_count' => $this->when(isset($this->resource->contacts_count), $this->resource->contacts_count),
                'created_at' => $this->resource->created_at?->toAtomString(),
                'updated_at' => $this->resource->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            ],
        ];
    }
}
