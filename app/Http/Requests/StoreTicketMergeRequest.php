<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTicketMergeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tickets.merge') ?? false;
    }

    public function rules(): array
    {
        return [
            'primary_ticket_id' => ['required', 'integer', 'min:1'],
            'secondary_ticket_id' => ['required', 'integer', 'min:1', 'different:primary_ticket_id'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
