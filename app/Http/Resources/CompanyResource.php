<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'name' => $this->resource->name,
            'domain' => $this->resource->domain,
            'metadata' => $this->resource->metadata ?? [],
            'contacts_count' => $this->when(isset($this->resource->contacts_count), $this->resource->contacts_count),
            'created_at' => optional($this->resource->created_at)->toISOString(),
            'updated_at' => optional($this->resource->updated_at)->toISOString(),
        ];
    }
}
