<?php

namespace App\Http\Requests;

class BroadcastAuthRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel_name' => ['required', 'string', 'max:255'],
            'socket_id' => ['required', 'string', 'max:255'],
            'channel_data' => ['sometimes', 'string'],
        ];
    }
}
