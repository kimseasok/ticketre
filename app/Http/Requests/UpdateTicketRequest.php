<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') ? app('currentTenant')->getKey() : null;
        $brandId = app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null;

        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['open', 'pending', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'contact_id' => ['sometimes', 'nullable', 'integer', Rule::exists('contacts', 'id')->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
            })],
            'company_id' => ['sometimes', 'nullable', 'integer', Rule::exists('companies', 'id')->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
            })],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where(function ($query) use ($tenantId, $brandId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }

                if ($brandId) {
                    $query->where(function ($sub) use ($brandId) {
                        $sub->whereNull('brand_id')->orWhere('brand_id', $brandId);
                    });
                }
            })],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ticket_departments', 'id')->where(function ($query) use ($tenantId, $brandId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }

                $query->where(function ($sub) use ($brandId) {
                    $sub->whereNull('brand_id');

                    if ($brandId) {
                        $sub->orWhere('brand_id', $brandId);
                    }
                });
            })],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('ticket_categories', 'id')->where(function ($query) use ($tenantId, $brandId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }

                $query->where(function ($sub) use ($brandId) {
                    $sub->whereNull('brand_id');

                    if ($brandId) {
                        $sub->orWhere('brand_id', $brandId);
                    }
                });
            })],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('ticket_tags', 'id')->where(function ($query) use ($tenantId, $brandId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }

                $query->where(function ($sub) use ($brandId) {
                    $sub->whereNull('brand_id');

                    if ($brandId) {
                        $sub->orWhere('brand_id', $brandId);
                    }
                });
            })],
            'workflow_state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sla_due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
