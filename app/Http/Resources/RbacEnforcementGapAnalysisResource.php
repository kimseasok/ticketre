<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RbacEnforcementGapAnalysis */
class RbacEnforcementGapAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'rbac-gap-analyses',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'status' => $this->status,
                'analysis_date' => $this->analysis_date?->toAtomString(),
                'audit_matrix' => $this->audit_matrix,
                'findings' => $this->findings,
                'remediation_plan' => $this->remediation_plan,
                'review_minutes' => $this->review_minutes,
                'notes' => $this->notes,
                'owner_team' => $this->owner_team,
                'reference_id' => $this->reference_id,
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
                'deleted_at' => $this->deleted_at?->toAtomString(),
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
                'self' => route('api.rbac-gap-analyses.show', $this->resource),
            ],
        ];
    }
}
