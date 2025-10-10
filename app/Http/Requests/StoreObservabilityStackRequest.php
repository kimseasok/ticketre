<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreObservabilityStackRequest extends ApiFormRequest
{
    private const STATUS_OPTIONS = ['evaluating', 'selected', 'deprecated'];
    private const LOGS_OPTIONS = ['elk', 'opensearch', 'loki-grafana'];
    private const METRICS_OPTIONS = ['prometheus', 'grafana-mimir', 'opensearch-metrics'];
    private const ALERTS_OPTIONS = ['grafana-alerting', 'pagerduty', 'opsgenie'];

    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('observability.stacks.manage') ?? false;
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
                Rule::unique('observability_stacks', 'slug')->where(function ($query) use ($tenantId, $brandId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');

                    if ($brandId === null) {
                        $query->whereNull('brand_id');
                    } else {
                        $query->where('brand_id', $brandId);
                    }

                    return $query;
                }),
            ],
            'status' => ['required', 'string', Rule::in(self::STATUS_OPTIONS)],
            'logs_tool' => ['required', 'string', 'max:255', Rule::in(self::LOGS_OPTIONS)],
            'metrics_tool' => ['required', 'string', 'max:255', Rule::in(self::METRICS_OPTIONS)],
            'alerts_tool' => ['required', 'string', 'max:255', Rule::in(self::ALERTS_OPTIONS)],
            'log_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'metric_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'trace_retention_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'estimated_monthly_cost' => ['nullable', 'numeric', 'min:0'],
            'trace_sampling_strategy' => ['nullable', 'string', 'max:255'],
            'decision_matrix' => ['nullable', 'array', 'min:1'],
            'decision_matrix.*.option' => ['required_with:decision_matrix', 'string', 'max:255'],
            'decision_matrix.*.monthly_cost' => ['required_with:decision_matrix', 'numeric', 'min:0'],
            'decision_matrix.*.scalability' => ['required_with:decision_matrix', 'string', 'max:1024'],
            'decision_matrix.*.notes' => ['nullable', 'string', 'max:2048'],
            'security_notes' => ['nullable', 'string'],
            'compliance_notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'brand_id' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
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
