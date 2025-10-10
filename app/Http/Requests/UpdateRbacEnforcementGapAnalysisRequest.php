<?php

namespace App\Http\Requests;

use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateRbacEnforcementGapAnalysisRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('security.rbac_gaps.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;

        $brandRule = Rule::exists('brands', 'id');

        if ($tenantId) {
            $brandRule = $brandRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'string', Rule::in(RbacEnforcementGapAnalysis::STATUSES)],
            'analysis_date' => ['sometimes', 'date'],
            'audit_matrix' => ['sometimes', 'array', 'min:1'],
            'audit_matrix.*.type' => ['required_with:audit_matrix', 'string', Rule::in(['route', 'command', 'queue'])],
            'audit_matrix.*.identifier' => ['required_with:audit_matrix', 'string', 'max:255'],
            'audit_matrix.*.required_permissions' => ['required_with:audit_matrix', 'array', 'min:1'],
            'audit_matrix.*.required_permissions.*' => ['string', 'min:1', 'max:150'],
            'audit_matrix.*.roles' => ['nullable', 'array'],
            'audit_matrix.*.roles.*' => ['string', 'min:1', 'max:120'],
            'audit_matrix.*.notes' => ['nullable', 'string', 'max:255'],
            'findings' => ['sometimes', 'array', 'min:1'],
            'findings.*.priority' => ['required_with:findings', 'string', Rule::in(['high', 'medium', 'low'])],
            'findings.*.summary' => ['required_with:findings', 'string', 'max:255'],
            'findings.*.owner' => ['nullable', 'string', 'max:120'],
            'findings.*.eta_days' => ['nullable', 'integer', 'between:0,365'],
            'findings.*.status' => ['nullable', 'string', 'max:64'],
            'remediation_plan' => ['sometimes', 'nullable', 'array'],
            'remediation_plan.*' => ['nullable'],
            'review_minutes' => ['sometimes', 'string', 'max:4000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'owner_team' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reference_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                $brandRule,
            ],
        ];
    }
}
