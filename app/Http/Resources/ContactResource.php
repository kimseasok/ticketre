<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Contact */
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
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'metadata' => $this->resource->metadata ?? [],
            'gdpr' => [
                'consent' => (bool) $this->resource->gdpr_consent,
                'consented_at' => optional($this->resource->gdpr_consented_at)->toIso8601String(),
                'consent_method' => $this->resource->gdpr_consent_method,
                'consent_source' => $this->resource->gdpr_consent_source,
                'notes_digest' => $this->resource->gdpr_notes ? hash('sha256', $this->resource->gdpr_notes) : null,
                'notes_present' => $this->resource->gdpr_notes !== null,
            ],
            'tags' => ContactTagResource::collection($this->whenLoaded('tags')),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }
}
