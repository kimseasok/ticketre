<?php

namespace App\Http\Requests;

use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;
use Illuminate\Validation\Rule;

class StoreRbacEnforcementGapAnalysisRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'string', Rule::in(RbacEnforcementGapAnalysis::STATUSES)],
            'analysis_date' => ['required', 'date'],
            'audit_matrix' => ['required', 'array', 'min:1'],
            'audit_matrix.*.type' => ['required', 'string', Rule::in(['route', 'command', 'queue'])],
            'audit_matrix.*.identifier' => ['required', 'string', 'max:255'],
            'audit_matrix.*.required_permissions' => ['required', 'array', 'min:1'],
            'audit_matrix.*.required_permissions.*' => ['string', 'min:1', 'max:150'],
            'audit_matrix.*.roles' => ['nullable', 'array'],
            'audit_matrix.*.roles.*' => ['string', 'min:1', 'max:120'],
            'audit_matrix.*.notes' => ['nullable', 'string', 'max:255'],
            'findings' => ['required', 'array', 'min:1'],
            'findings.*.priority' => ['required', 'string', Rule::in(['high', 'medium', 'low'])],
            'findings.*.summary' => ['required', 'string', 'max:255'],
            'findings.*.owner' => ['nullable', 'string', 'max:120'],
            'findings.*.eta_days' => ['nullable', 'integer', 'between:0,365'],
            'findings.*.status' => ['nullable', 'string', 'max:64'],
            'remediation_plan' => ['nullable', 'array'],
            'remediation_plan.*' => ['nullable'],
            'review_minutes' => ['required', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_team' => ['nullable', 'string', 'max:120'],
            'reference_id' => ['nullable', 'string', 'max:64'],
            'brand_id' => [
                'nullable',
                'integer',
                $brandRule,
            ],
        ];
    }
}
