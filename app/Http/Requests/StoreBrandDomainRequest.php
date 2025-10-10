<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBrandDomainRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('brand_domains.manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[A-Za-z]{2,}$/',
                Rule::unique('brand_domains', 'domain')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'verification_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
