<?php

namespace App\Http\Requests;

use App\Models\TicketEvent;
use Illuminate\Validation\Rule;

class StoreTicketEventRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                TicketEvent::TYPE_CREATED,
                TicketEvent::TYPE_UPDATED,
                TicketEvent::TYPE_ASSIGNED,
                TicketEvent::TYPE_MERGED,
            ])],
            'visibility' => ['sometimes', Rule::in([
                TicketEvent::VISIBILITY_INTERNAL,
                TicketEvent::VISIBILITY_PUBLIC,
            ])],
            'payload' => ['nullable', 'array'],
        ];
    }
}
