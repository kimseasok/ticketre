<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        /** @var Company $company */
        $company = $this->route('company');

        return Gate::forUser($user)->allows('update', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');
        $tenantId = $this->user()?->tenant_id ?? app('currentTenant')?->getKey() ?? 0;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', $this->uniqueDomainRule($tenantId, $company)],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }

    protected function uniqueDomainRule(int $tenantId, Company $company)
    {
        return Rule::unique('companies', 'domain')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->ignore($company->getKey());
    }
}
