<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreHorizonDeploymentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('infrastructure.horizon.manage') ?? false;
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
                Rule::unique('horizon_deployments', 'slug')
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
            'brand_id' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'domain' => ['required', 'string', 'max:255'],
            'auth_guard' => ['nullable', 'string', 'max:64'],
            'horizon_connection' => ['nullable', 'string', 'max:64'],
            'uses_tls' => ['sometimes', 'boolean'],
            'supervisors' => ['required', 'array', 'min:1'],
            'supervisors.*.name' => ['required', 'string', 'max:64'],
            'supervisors.*.connection' => ['nullable', 'string', 'max:64'],
            'supervisors.*.queue' => ['required', 'array', 'min:1'],
            'supervisors.*.queue.*' => ['string', 'max:64'],
            'supervisors.*.balance' => ['nullable', 'string', 'max:32'],
            'supervisors.*.min_processes' => ['nullable', 'integer', 'min:0', 'max:100'],
            'supervisors.*.max_processes' => ['nullable', 'integer', 'min:1', 'max:200'],
            'supervisors.*.max_jobs' => ['nullable', 'integer', 'min:0'],
            'supervisors.*.max_time' => ['nullable', 'integer', 'min:0'],
            'supervisors.*.timeout' => ['nullable', 'integer', 'min:1', 'max:900'],
            'supervisors.*.tries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'last_deployed_at' => ['nullable', 'date'],
            'ssl_certificate_expires_at' => ['nullable', 'date', 'after:today'],
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
