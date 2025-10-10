<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ObservabilityStack */
class ObservabilityStackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'observability-stacks',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'name' => $this->name,
                'name_digest' => $this->nameDigest(),
                'slug' => $this->slug,
                'status' => $this->status,
                'logs_tool' => $this->logs_tool,
                'logs_tool_digest' => $this->logsToolDigest(),
                'metrics_tool' => $this->metrics_tool,
                'metrics_tool_digest' => $this->metricsToolDigest(),
                'alerts_tool' => $this->alerts_tool,
                'log_retention_days' => $this->log_retention_days,
                'metric_retention_days' => $this->metric_retention_days,
                'trace_retention_days' => $this->trace_retention_days,
                'estimated_monthly_cost' => $this->estimated_monthly_cost !== null
                    ? (float) $this->estimated_monthly_cost
                    : null,
                'trace_sampling_strategy' => $this->trace_sampling_strategy,
                'decision_matrix' => $this->decision_matrix,
                'security_notes' => $this->security_notes,
                'compliance_notes' => $this->compliance_notes,
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
                'self' => route('api.observability-stacks.show', $this->resource),
            ],
        ];
    }
}
