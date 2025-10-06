<?php

namespace App\Http\Requests;

use App\Models\TicketRelationship;
use Illuminate\Validation\Rule;

class StoreTicketRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        return [
            'related_ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'relationship_type' => ['required', 'string', Rule::in(TicketRelationship::allowedTypes())],
            'context' => ['nullable', 'array'],
        ];
    }
}
