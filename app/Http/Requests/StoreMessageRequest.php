<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'visibility' => ['required', Rule::in([Message::VISIBILITY_PUBLIC, Message::VISIBILITY_INTERNAL])],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
