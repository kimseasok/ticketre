<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ObservabilityPipeline */
class ObservabilityPipelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'observability-pipelines',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'pipeline_type' => $this->pipeline_type,
                'ingest_endpoint' => $this->ingest_endpoint,
                'ingest_endpoint_digest' => $this->ingestEndpointDigest(),
                'ingest_endpoint_preview' => $this->ingestEndpointPreview(),
                'ingest_protocol' => $this->ingest_protocol,
                'buffer_strategy' => $this->buffer_strategy,
                'buffer_retention_seconds' => $this->buffer_retention_seconds,
                'retry_backoff_seconds' => $this->retry_backoff_seconds,
                'max_retry_attempts' => $this->max_retry_attempts,
                'batch_max_bytes' => $this->batch_max_bytes,
                'metrics_scrape_interval_seconds' => $this->metrics_scrape_interval_seconds,
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
                'self' => route('api.observability-pipelines.show', $this->resource),
            ],
        ];
    }
}
