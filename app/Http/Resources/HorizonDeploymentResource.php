<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HorizonDeploymentResource extends JsonResource
{
    /**
     * @param  \App\Models\HorizonDeployment  $resource
     */
    public function toArray($request): array
    {
        return [
            'type' => 'horizon-deployments',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'slug' => $this->resource->slug,
                'domain' => $this->resource->domain,
                'domain_digest' => $this->resource->domainDigest(),
                'auth_guard' => $this->resource->auth_guard,
                'horizon_connection' => $this->resource->horizon_connection,
                'uses_tls' => $this->resource->uses_tls,
                'supervisors' => $this->resource->supervisors,
                'last_deployed_at' => optional($this->resource->last_deployed_at)->toAtomString(),
                'ssl_certificate_expires_at' => optional($this->resource->ssl_certificate_expires_at)->toAtomString(),
                'last_health_status' => $this->resource->last_health_status,
                'last_health_checked_at' => optional($this->resource->last_health_checked_at)->toAtomString(),
                'last_health_report' => $this->resource->last_health_report,
                'metadata' => $this->resource->metadata,
                'created_at' => optional($this->resource->created_at)->toAtomString(),
                'updated_at' => optional($this->resource->updated_at)->toAtomString(),
            ],
            'relationships' => [
                'tenant' => [
                    'data' => $this->whenLoaded('tenant', fn () => [
                        'type' => 'tenants',
                        'id' => (string) $this->resource->tenant->getKey(),
                        'attributes' => [
                            'name' => $this->resource->tenant->name,
                            'slug' => $this->resource->tenant->slug,
                        ],
                    ]),
                ],
                'brand' => [
                    'data' => $this->whenLoaded('brand', fn () => [
                        'type' => 'brands',
                        'id' => (string) $this->resource->brand->getKey(),
                        'attributes' => [
                            'name' => $this->resource->brand->name,
                            'slug' => $this->resource->brand->slug,
                        ],
                    ]),
                ],
            ],
        ];
    }
}
