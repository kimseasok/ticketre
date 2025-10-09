<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class UpdateContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $contact = $this->route('contact');

        if (! $user || ! $contact instanceof Contact) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $contact);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Contact|null $contact */
        $contact = $this->route('contact');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', $this->uniqueEmailRule($contact)],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', $this->companyExistsRule()],
            'brand_id' => ['nullable', $this->brandExistsRule()],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:64'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'gdpr_marketing_opt_in' => ['sometimes', 'accepted'],
            'gdpr_data_processing_opt_in' => ['sometimes', 'accepted'],
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

    protected function uniqueEmailRule(?Contact $contact): Unique
    {
        $rule = Rule::unique('contacts', 'email')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));

        if ($contact) {
            $rule = $rule->ignore($contact->getKey());
        }

        return $rule;
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
