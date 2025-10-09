<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class TwoFactorChallengeRequest extends ApiFormRequest
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
            'code' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'recovery_code' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('code') && ! $this->filled('recovery_code')) {
                $validator->errors()->add('code', 'An authentication code or recovery code is required.');
            }
        });
    }
}
