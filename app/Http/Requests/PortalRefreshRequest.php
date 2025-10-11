<?php

namespace App\Http\Requests;

class PortalRefreshRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'min:32'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
