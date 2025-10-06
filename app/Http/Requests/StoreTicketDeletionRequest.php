<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketDeletionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('tickets.redact') : false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? 0;

        return [
            'ticket_id' => [
                'required',
                'integer',
                Rule::exists('tickets', 'id')->where(function ($query) use ($tenantId) {
                    return $query
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                }),
            ],
            'reason' => ['required', 'string', 'max:500'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        if (! $validator instanceof Validator) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $ticketId = (int) $this->input('ticket_id');

            if (! $ticketId) {
                return;
            }

            $ticket = Ticket::withoutGlobalScopes()
                ->where('tenant_id', $this->user()?->tenant_id)
                ->whereKey($ticketId)
                ->first();

            if (! $ticket) {
                return;
            }

            if (($ticket->metadata['redacted'] ?? false) === true) {
                $validator->errors()->add('ticket_id', 'This ticket has already been redacted.');
                return;
            }

            $exists = TicketDeletionRequest::withoutGlobalScopes()
                ->where('tenant_id', $ticket->tenant_id)
                ->where('ticket_id', $ticket->getKey())
                ->whereNotIn('status', [
                    TicketDeletionRequest::STATUS_COMPLETED,
                    TicketDeletionRequest::STATUS_CANCELLED,
                    TicketDeletionRequest::STATUS_FAILED,
                ])
                ->exists();

            if ($exists) {
                $validator->errors()->add('ticket_id', 'An active deletion request already exists for this ticket.');
            }
        });
    }
}
