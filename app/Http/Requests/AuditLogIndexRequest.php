<?php

namespace App\Http\Requests;

class AuditLogIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->can('audit_logs.view');
    }

    public function rules(): array
    {
        return [
            'auditable_type' => ['nullable', 'string', 'in:ticket,contact,message,kb_article,kb_category'],
            'auditable_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
