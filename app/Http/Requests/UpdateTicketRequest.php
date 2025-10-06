<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['open', 'pending', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'contact_id' => ['sometimes', 'nullable', 'integer', 'exists:contacts,id'],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'workflow_state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sla_due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
