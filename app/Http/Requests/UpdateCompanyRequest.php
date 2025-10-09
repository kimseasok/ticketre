<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class UpdateCompanyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');

        if (! $user || ! $company instanceof Company) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $company);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255', $this->uniqueDomainRule($company)],
            'brand_id' => ['nullable', $this->brandExistsRule()],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:64'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $brandId = $this->input('brand_id');
            $user = $this->user();

            if ($user && $user->brand_id && $brandId && (int) $brandId !== (int) $user->brand_id) {
                $validator->errors()->add('brand_id', 'You may only manage companies within your assigned brand.');
            }
        });
    }

    protected function uniqueDomainRule(?Company $company): Unique
    {
        $rule = Rule::unique('companies', 'domain')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));

        if ($company) {
            $rule = $rule->ignore($company->getKey());
        }

        return $rule;
    }

    protected function brandExistsRule(): Exists
    {
        return Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));
    }

    protected function tenantId(): int
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant?->getKey() ?? $this->user()?->tenant_id ?? 0;
    }
}
