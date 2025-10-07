<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');

        if (! $user || ! $company instanceof Company) {
            return false;
        }

        return $user->can('update', $company);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();
        $company = $this->route('company');

        $companyId = $company instanceof Company ? $company->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'domain' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('companies', 'domain')
                    ->ignore($companyId)
                    ->where(function ($query) use ($tenantId) {
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId);
                        }

                        return $query->whereNull('deleted_at');
                    }),
            ],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    protected function resolveTenantId(): ?int
    {
        if (app()->bound('currentTenant') && app('currentTenant')) {
            return (int) app('currentTenant')->getKey();
        }

        return $this->user()?->tenant_id;
    }
}
