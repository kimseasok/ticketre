<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'contacts',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'phone' => $this->resource->phone,
                'tenant_id' => $this->resource->tenant_id,
                'brand_id' => $this->resource->brand_id,
                'company_id' => $this->resource->company_id,
                'tags' => $this->resource->tags ?? [],
                'metadata' => $this->resource->metadata ?? [],
                'gdpr_marketing_opt_in' => $this->resource->gdpr_marketing_opt_in,
                'gdpr_data_processing_opt_in' => $this->resource->gdpr_data_processing_opt_in,
                'created_at' => $this->resource->created_at?->toAtomString(),
                'updated_at' => $this->resource->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'company' => CompanyResource::make($this->whenLoaded('company')),
            ],
        ];
    }
}
