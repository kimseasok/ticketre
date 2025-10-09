<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CiQualityGate */
class CiQualityGateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'ci-quality-gates',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'coverage_threshold' => (float) $this->coverage_threshold,
                'max_critical_vulnerabilities' => $this->max_critical_vulnerabilities,
                'max_high_vulnerabilities' => $this->max_high_vulnerabilities,
                'enforce_dependency_audit' => $this->enforce_dependency_audit,
                'enforce_docker_build' => $this->enforce_docker_build,
                'notifications_enabled' => $this->notifications_enabled,
                'notify_channel_digest' => $this->notifyChannelDigest(),
                'metadata' => $this->metadata,
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
                'brand' => [
                    'data' => $this->when($this->relationLoaded('brand') && $this->brand, function () {
                        return [
                            'type' => 'brands',
                            'id' => (string) $this->brand->getKey(),
                            'attributes' => [
                                'name' => $this->brand->name,
                                'slug' => $this->brand->slug,
                            ],
                        ];
                    }),
                ],
            ],
            'links' => [
                'self' => route('api.ci-quality-gates.show', $this->resource),
            ],
        ];
    }
}
