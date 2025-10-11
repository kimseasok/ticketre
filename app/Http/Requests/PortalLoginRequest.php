<?php

namespace App\Http\Requests;

class PortalLoginRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
