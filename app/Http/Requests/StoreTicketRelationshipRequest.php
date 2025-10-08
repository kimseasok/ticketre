<?php

namespace App\Http\Requests;

use App\Models\TicketRelationship;
use Illuminate\Validation\Rule;

class StoreTicketRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TicketRelationship::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primary_ticket_id' => ['required', 'integer', 'min:1'],
            'related_ticket_id' => ['required', 'integer', 'min:1'],
            'relationship_type' => ['required', 'string', Rule::in(TicketRelationship::TYPES)],
            'context' => ['sometimes', 'array'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
