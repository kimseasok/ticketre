<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Company::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? app('currentTenant')?->getKey() ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255', $this->uniqueDomainRule($tenantId)],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }

    protected function uniqueDomainRule(int $tenantId)
    {
        return Rule::unique('companies', 'domain')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');
    }
}
