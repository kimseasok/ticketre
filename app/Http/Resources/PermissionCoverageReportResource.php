<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PermissionCoverageReport */
class PermissionCoverageReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'permission-coverage-reports',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'module' => $this->module,
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'total_routes' => $this->total_routes,
                'guarded_routes' => $this->guarded_routes,
                'unguarded_routes' => $this->unguarded_routes,
                'coverage' => (float) $this->coverage,
                'unguarded_paths' => $this->unguarded_paths,
                'metadata' => $this->metadata,
                'notes' => $this->notes,
                'generated_at' => $this->generated_at?->toAtomString(),
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
                'self' => route('api.permission-coverage-reports.show', $this->resource),
            ],
        ];
    }
}
