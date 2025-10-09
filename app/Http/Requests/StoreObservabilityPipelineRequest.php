<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreObservabilityPipelineRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('observability.pipelines.manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $brandId = $this->input('brand_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('observability_pipelines', 'slug')
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
            'pipeline_type' => ['required', 'string', Rule::in(['logs', 'metrics', 'traces'])],
            'ingest_endpoint' => ['required', 'string', 'max:2048'],
            'ingest_protocol' => ['nullable', 'string', 'max:32'],
            'buffer_strategy' => ['nullable', 'string', 'max:64'],
            'buffer_retention_seconds' => ['required', 'integer', 'min:0'],
            'retry_backoff_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'max_retry_attempts' => ['required', 'integer', 'min:0', 'max:100'],
            'batch_max_bytes' => ['required', 'integer', 'min:1024'],
            'metrics_scrape_interval_seconds' => [
                'nullable',
                'integer',
                'min:5',
                'max:3600',
                Rule::requiredIf(fn () => $this->input('pipeline_type') === 'metrics'),
            ],
            'brand_id' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'metadata' => ['nullable', 'array'],
        ];
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
