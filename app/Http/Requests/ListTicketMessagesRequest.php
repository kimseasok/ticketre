<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTicketMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audience' => 'sometimes|string|in:agent,portal',
        ];
    }
}
