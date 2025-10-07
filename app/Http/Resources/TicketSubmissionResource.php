<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketSubmissionResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        /** @var \App\Models\TicketSubmission $submission */
        $submission = $this->resource;

        $metadata = $submission->metadata ?? [];
        $attachmentsCount = $submission->attachments_count
            ?? ($submission->relationLoaded('attachments') ? $submission->attachments->count() : null);

        return [
            'id' => $submission->getKey(),
            'subject' => $submission->subject,
            'message' => $submission->message,
            'status' => $submission->status,
            'channel' => $submission->channel,
            'tags' => $submission->tags ?? [],
            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
            'correlation_id' => $submission->correlation_id,
            'ticket' => $this->whenLoaded('ticket', function () use ($submission) {
                return [
                    'id' => $submission->ticket_id,
                    'status' => $submission->ticket?->status,
                    'priority' => $submission->ticket?->priority,
                    'channel' => $submission->ticket?->channel,
                ];
            }, [
                'id' => $submission->ticket_id,
            ]),
            'contact' => $this->whenLoaded('contact', function () use ($submission) {
                return [
                    'id' => $submission->contact?->getKey(),
                    'name' => $submission->contact?->name,
                    'email' => $submission->contact?->email,
                ];
            }),
            'attachments' => [
                'count' => $attachmentsCount,
            ],
            'metadata' => array_filter([
                'ip_hash' => $metadata['ip_hash'] ?? null,
                'filename_digests' => $metadata['filename_digests'] ?? null,
            ], static fn ($value) => $value !== null),
        ];
    }
}
