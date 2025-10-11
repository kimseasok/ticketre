<?php

namespace App\Http\Requests;

class PortalSessionIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:active,revoked'],
            'portal_account_id' => ['sometimes', 'integer'],
            'brand_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
