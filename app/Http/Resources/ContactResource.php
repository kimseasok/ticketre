<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'company_id' => $this->resource->company_id,
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->resource->tags->pluck('name')->sort()->values()->all(),
                []
            ),
            'gdpr_marketing_opt_in' => $this->resource->gdpr_marketing_opt_in,
            'gdpr_tracking_opt_in' => $this->resource->gdpr_tracking_opt_in,
            'gdpr_consent_recorded_at' => optional($this->resource->gdpr_consent_recorded_at)->toISOString(),
            'metadata' => $this->resource->metadata ?? [],
            'created_at' => optional($this->resource->created_at)->toISOString(),
            'updated_at' => optional($this->resource->updated_at)->toISOString(),
        ];
    }
}
