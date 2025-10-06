<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Message::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|min:3',
            'visibility' => 'sometimes|string|in:'.implode(',', [Message::VISIBILITY_PUBLIC, Message::VISIBILITY_INTERNAL]),
            'sent_at' => 'sometimes|date',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_FORBIDDEN',
                'message' => 'You are not allowed to create messages.',
            ],
        ], 403));
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_VALIDATION',
                'message' => $validator->errors()->first() ?? 'Validation failed.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
