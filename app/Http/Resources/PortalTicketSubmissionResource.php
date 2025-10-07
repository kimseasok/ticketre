<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PortalTicketSubmissionResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        /** @var \App\Models\TicketSubmission $submission */
        $submission = $this->resource;

        return [
            'id' => $submission->getKey(),
            'reference' => sprintf('TKT-%06d', $submission->ticket_id),
            'ticket_id' => $submission->ticket_id,
            'status' => $submission->status,
            'channel' => $submission->channel,
            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
            'correlation_id' => $submission->correlation_id,
            'message' => [
                'preview' => Str::limit($submission->message, 140),
            ],
            'links' => [
                'confirmation' => route('portal.tickets.confirmation', ['submission' => $submission->getKey()], false),
            ],
        ];
    }
}
