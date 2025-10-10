<?php

namespace App\Http\Requests;

class VerifyBrandDomainRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('brand_domains.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
