<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'required', 'string'],
            'visibility' => ['sometimes', 'required', Rule::in([Message::VISIBILITY_PUBLIC, Message::VISIBILITY_INTERNAL])],
            'sent_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
