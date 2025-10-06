<?php

namespace App\Http\Requests;

class ApproveTicketDeletionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('tickets.redact') : false;
    }

    public function rules(): array
    {
        return [
            'hold_hours' => ['nullable', 'integer', 'between:0,168'],
        ];
    }
}
