<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['open', 'pending', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
            'department' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'workflow_state' => ['nullable', 'string', 'max:255'],
            'sla_due_at' => ['nullable', 'date'],
        ];
    }
}
