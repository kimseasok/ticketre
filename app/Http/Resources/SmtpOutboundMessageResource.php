<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SmtpOutboundMessage */
class SmtpOutboundMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'smtp-outbound-messages',
            'id' => (string) $this->getKey(),
            'attributes' => [
                'tenant_id' => $this->tenant_id,
                'brand_id' => $this->brand_id,
                'ticket_id' => $this->ticket_id,
                'message_id' => $this->message_id,
                'status' => $this->status,
                'mailer' => $this->mailer,
                'subject' => $this->subject,
                'from_email' => $this->from_email,
                'from_name' => $this->from_name,
                'to' => $this->to ?? [],
                'cc' => $this->cc ?? [],
                'bcc' => $this->bcc ?? [],
                'reply_to' => $this->reply_to ?? [],
                'headers' => $this->headers ?? [],
                'attachments' => $this->attachments ?? [],
                'body_html' => $this->body_html,
                'body_text' => $this->body_text,
                'attempts' => $this->attempts,
                'queued_at' => $this->queued_at?->toAtomString(),
                'dispatched_at' => $this->dispatched_at?->toAtomString(),
                'delivered_at' => $this->delivered_at?->toAtomString(),
                'failed_at' => $this->failed_at?->toAtomString(),
                'last_error' => $this->last_error,
                'correlation_id' => $this->correlation_id,
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ],
            'relationships' => [
                'ticket' => [
                    'data' => $this->whenLoaded('ticket', function () {
                        return [
                            'type' => 'tickets',
                            'id' => (string) $this->ticket?->getKey(),
                            'attributes' => [
                                'subject' => $this->ticket?->subject,
                                'status' => $this->ticket?->status,
                            ],
                        ];
                    }),
                ],
                'message' => [
                    'data' => $this->whenLoaded('message', function () {
                        return [
                            'type' => 'messages',
                            'id' => (string) $this->message?->getKey(),
                            'attributes' => [
                                'visibility' => $this->message?->visibility,
                                'author_role' => $this->message?->author_role,
                            ],
                        ];
                    }),
                ],
            ],
            'links' => [
                'self' => route('api.smtp-outbound-messages.show', $this->resource),
            ],
        ];
    }
}
