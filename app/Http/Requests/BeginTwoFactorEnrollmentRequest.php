<?php

namespace App\Http\Requests;

class BeginTwoFactorEnrollmentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('security.2fa.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
