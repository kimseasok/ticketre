<?php

namespace App\Http\Requests;

use App\Models\TicketSubmission;
use Illuminate\Validation\Rule;

class TicketSubmissionIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('tickets.view');
    }

    public function rules(): array
    {
        return [
            'channel' => ['sometimes', 'string', Rule::in([
                TicketSubmission::CHANNEL_PORTAL,
                TicketSubmission::CHANNEL_EMAIL,
                TicketSubmission::CHANNEL_CHAT,
                TicketSubmission::CHANNEL_API,
            ])],
            'status' => ['sometimes', 'string', Rule::in([
                TicketSubmission::STATUS_ACCEPTED,
                TicketSubmission::STATUS_PENDING,
                TicketSubmission::STATUS_FAILED,
            ])],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
