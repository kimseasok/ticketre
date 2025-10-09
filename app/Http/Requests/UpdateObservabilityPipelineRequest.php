<?php

namespace App\Http\Requests;

use App\Models\ObservabilityPipeline;
use Illuminate\Validation\Rule;

class UpdateObservabilityPipelineRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('observability.pipelines.manage') ?? false;
    }

    public function rules(): array
    {
        /** @var ObservabilityPipeline|null $pipeline */
        $pipeline = $this->route('observability_pipeline');
        $tenantId = $pipeline?->tenant_id ?? $this->tenantId();
        $brandId = $this->input('brand_id', $pipeline?->brand_id);
        $pipelineType = $this->input('pipeline_type', $pipeline?->pipeline_type);

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('observability_pipelines', 'slug')
                    ->ignore($pipeline?->getKey(), 'id')
                    ->where(function ($query) use ($tenantId, $brandId) {
                        $query->where('tenant_id', $tenantId)->whereNull('deleted_at');

                        if ($brandId === null) {
                            $query->whereNull('brand_id');
                        } else {
                            $query->where('brand_id', $brandId);
                        }

                        return $query;
                    }),
            ],
            'pipeline_type' => ['sometimes', 'string', Rule::in(['logs', 'metrics', 'traces'])],
            'ingest_endpoint' => ['sometimes', 'string', 'max:2048'],
            'ingest_protocol' => ['sometimes', 'nullable', 'string', 'max:32'],
            'buffer_strategy' => ['sometimes', 'nullable', 'string', 'max:64'],
            'buffer_retention_seconds' => ['sometimes', 'integer', 'min:0'],
            'retry_backoff_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'max_retry_attempts' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'batch_max_bytes' => ['sometimes', 'integer', 'min:1024'],
            'metrics_scrape_interval_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'min:5',
                'max:3600',
            ],
            'brand_id' => [
                'sometimes',
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];

        if ($pipelineType === 'metrics' && ! $this->has('metrics_scrape_interval_seconds')) {
            $this->merge([
                'metrics_scrape_interval_seconds' => $pipeline?->metrics_scrape_interval_seconds,
            ]);
        }

        if ($pipelineType === 'metrics' && ($this->input('metrics_scrape_interval_seconds') === null || $this->input('metrics_scrape_interval_seconds') === '')) {
            $this->merge([
                'metrics_scrape_interval_seconds' => $pipeline?->metrics_scrape_interval_seconds ?? config('observability.defaults.metrics_scrape_interval_seconds'),
            ]);
        }

        return $rules;
    }

    protected function tenantId(): ?int
    {
        if ($this->user()) {
            return $this->user()->tenant_id;
        }

        if (app()->bound('currentTenant') && app('currentTenant')) {
            return app('currentTenant')->getKey();
        }

        return null;
    }
}
