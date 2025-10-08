<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCustomFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ApiFormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? (int) app('currentTenant')->getKey() : null;
        $brandId = app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null;

        $contactRule = Rule::exists('contacts', 'id');
        $companyRule = Rule::exists('companies', 'id');
        $assigneeRule = Rule::exists('users', 'id');

        if ($tenantId) {
            $contactRule->where('tenant_id', $tenantId);
            $companyRule->where('tenant_id', $tenantId);
            $assigneeRule->where('tenant_id', $tenantId);
        }

        if ($brandId !== null) {
            $assigneeRule->where(function ($query) use ($brandId) {
                $query->whereNull('brand_id')->orWhere('brand_id', $brandId);
            });
        }

        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['open', 'pending', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'contact_id' => ['sometimes', 'nullable', 'integer', $contactRule],
            'company_id' => ['sometimes', 'nullable', 'integer', $companyRule],
            'assignee_id' => ['sometimes', 'nullable', 'integer', $assigneeRule],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'workflow_state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'workflow_context' => ['sometimes', 'array'],
            'workflow_context.comment' => ['nullable', 'string', 'max:1000'],
            'sla_due_at' => ['sometimes', 'nullable', 'date'],
        ] + $this->customFieldRules(true);
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateCustomFields($validator);
    }
}
