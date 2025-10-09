<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreCiQualityGateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('ci.quality_gates.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;
        $brandId = app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('ci_quality_gates', 'slug')->when($tenantId, function ($query) use ($tenantId, $brandId) {
                    $query->where('tenant_id', $tenantId);

                    if ($brandId) {
                        $query->where(function ($builder) use ($brandId) {
                            $builder->whereNull('brand_id')->orWhere('brand_id', $brandId);
                        });
                    }
                }),
            ],
            'coverage_threshold' => ['required', 'numeric', 'between:0,100'],
            'max_critical_vulnerabilities' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_high_vulnerabilities' => ['required', 'integer', 'min:0', 'max:1000'],
            'enforce_dependency_audit' => ['sometimes', 'boolean'],
            'enforce_docker_build' => ['sometimes', 'boolean'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'notify_channel' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.owner' => ['nullable', 'string', 'max:120'],
            'metadata.description' => ['nullable', 'string'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
