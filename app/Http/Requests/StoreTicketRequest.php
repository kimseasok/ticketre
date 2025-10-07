<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCustomFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends ApiFormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['open', 'pending', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'contact_id' => ['nullable', 'integer', $contactRule],
            'company_id' => ['nullable', 'integer', $companyRule],
            'assignee_id' => ['nullable', 'integer', $assigneeRule],
            'metadata' => ['nullable', 'array'],
            'department' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'workflow_state' => ['nullable', 'string', 'max:255'],
            'sla_due_at' => ['nullable', 'date'],
        ] + $this->customFieldRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateCustomFields($validator);
    }
}
