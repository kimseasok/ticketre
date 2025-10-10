<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BrandDomain */
class BrandDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'brand-domains',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'domain' => $this->domain,
                'domain_digest' => $this->domainDigest(),
                'status' => $this->status,
                'ssl_status' => $this->ssl_status,
                'dns_checked_at' => $this->dns_checked_at?->toAtomString(),
                'ssl_checked_at' => $this->ssl_checked_at?->toAtomString(),
                'verified_at' => $this->verified_at?->toAtomString(),
                'verification_error' => $this->verification_error,
                'ssl_error' => $this->ssl_error,
                'dns_records' => $this->dns_records,
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
                'self' => route('api.brand-domains.show', $this->resource),
                'verify' => route('api.brand-domains.verify', $this->resource),
            ],
        ];
    }
}
