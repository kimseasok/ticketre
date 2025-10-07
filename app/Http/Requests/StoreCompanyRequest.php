<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('create', Company::class) : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('companies', 'domain')->where(function ($query) use ($tenantId) {
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
