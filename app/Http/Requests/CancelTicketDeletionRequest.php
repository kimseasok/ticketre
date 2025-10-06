<?php

namespace App\Http\Requests;

class CancelTicketDeletionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('tickets.redact') : false;
    }

    public function rules(): array
    {
        return [];
    }
}
