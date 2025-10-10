<?php

namespace App\Http\Requests;

use App\Models\PermissionCoverageReport;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdatePermissionCoverageReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('security.permission_coverage.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;

        return [
            'module' => ['sometimes', 'string', Rule::in(PermissionCoverageReport::MODULES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'metadata.*' => ['nullable'],
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
