<?php

namespace App\Http\Requests;

use App\Models\TicketRelationship;
use Illuminate\Validation\Rule;

class UpdateTicketRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        return [
            'relationship_type' => ['sometimes', 'required', 'string', Rule::in(TicketRelationship::allowedTypes())],
            'context' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
