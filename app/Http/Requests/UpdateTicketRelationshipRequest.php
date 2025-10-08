<?php

namespace App\Http\Requests;

use App\Models\TicketRelationship;
use Illuminate\Validation\Rule;

class UpdateTicketRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var TicketRelationship $relationship */
        $relationship = $this->route('ticket_relationship');

        return $this->user()?->can('update', $relationship) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relationship_type' => ['sometimes', 'string', Rule::in(TicketRelationship::TYPES)],
            'context' => ['sometimes', 'array'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
