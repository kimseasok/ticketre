<?php

namespace App\Http\Requests;

use App\Models\BrandDomain;
use Illuminate\Validation\Rule;

class UpdateBrandDomainRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        /** @var BrandDomain $brandDomain */
        $brandDomain = $this->route('brand_domain');

        return $user?->can('brand_domains.manage') && $user->tenant_id === $brandDomain->tenant_id;
    }

    public function rules(): array
    {
        /** @var BrandDomain $brandDomain */
        $brandDomain = $this->route('brand_domain');
        $tenantId = $this->user()?->tenant_id;

        return [
            'domain' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[A-Za-z]{2,}$/',
                Rule::unique('brand_domains', 'domain')
                    ->ignore($brandDomain->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'verification_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
