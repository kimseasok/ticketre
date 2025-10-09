<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class StoreContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Contact::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $this->uniqueEmailRule()],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', $this->companyExistsRule()],
            'brand_id' => ['nullable', $this->brandExistsRule()],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:64'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'gdpr_marketing_opt_in' => ['required', 'accepted'],
            'gdpr_data_processing_opt_in' => ['required', 'accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $companyId = $this->input('company_id');
            $brandId = $this->input('brand_id');
            $user = $this->user();

            if ($companyId) {
                $company = Company::query()->find($companyId);

                if ($company && $company->tenant_id !== $this->tenantId()) {
                    $validator->errors()->add('company_id', 'The selected company is not accessible within this tenant.');
                }

                if ($company && $brandId && $company->brand_id && (int) $brandId !== (int) $company->brand_id) {
                    $validator->errors()->add('company_id', 'The selected company does not belong to the specified brand.');
                }
            }

            if ($user && $user->brand_id && $brandId && (int) $brandId !== (int) $user->brand_id) {
                $validator->errors()->add('brand_id', 'You may only manage contacts within your assigned brand.');
            }
        });
    }

    protected function uniqueEmailRule(): Unique
    {
        return Rule::unique('contacts', 'email')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));
    }

    protected function companyExistsRule(): Exists
    {
        return Rule::exists('companies', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));
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
