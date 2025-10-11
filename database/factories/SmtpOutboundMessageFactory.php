<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Contact;
use App\Models\Message;
use App\Models\SmtpOutboundMessage;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SmtpOutboundMessageFactory extends Factory
{
    protected $model = SmtpOutboundMessage::class;

    public function definition(): array
    {
        $ticketId = $this->attributes['ticket_id'] ?? null;

        if ($ticketId) {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->findOrFail($ticketId);
        } else {
            /** @var Ticket $ticket */
            $ticket = Ticket::factory()->create();
        }

        /** @var Brand|null $brand */
        $brand = $ticket->brand()->withoutGlobalScopes()->first();
        $brandDomain = $brand?->domain ?? $this->faker->domainName();
        $brandName = $brand?->name ?? $this->faker->company();

        /** @var Contact|null $contact */
        $contact = $ticket->contact()->withoutGlobalScopes()->first();
        $contactEmail = $contact?->email ?? $this->faker->safeEmail();
        $contactName = $contact?->name ?? $this->faker->name();

        $messageId = $this->attributes['message_id'] ?? null;

        if ($messageId) {
            Message::query()
                ->where('ticket_id', $ticket->getKey())
                ->findOrFail($messageId);
        } else {
            /** @var Message $message */
            $message = Message::factory()->create([
                'tenant_id' => $ticket->tenant_id,
                'brand_id' => $ticket->brand_id,
                'ticket_id' => $ticket->getKey(),
            ]);
            $messageId = $message->getKey();
        }

        return [
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'message_id' => $messageId,
            'status' => SmtpOutboundMessage::STATUS_QUEUED,
            'mailer' => 'smtp',
            'subject' => $this->faker->sentence(6),
            'from_email' => 'support@'.$brandDomain,
            'from_name' => $brandName.' Support',
            'to' => [
                [
                    'email' => $contactEmail,
                    'name' => $contactName,
                ],
            ],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [
                [
                    'email' => 'noreply@'.$brandDomain,
                    'name' => $brandName.' Queue',
                ],
            ],
            'headers' => [
                'X-Ticket-ID' => (string) $ticket->getKey(),
                'X-Correlation-ID' => Str::uuid()->toString(),
            ],
            'attachments' => [
                [
                    'disk' => 'public',
                    'path' => 'tickets/'.$ticket->getKey().'/attachments/'.$this->faker->uuid().'.txt',
                    'name' => 'transcript.txt',
                    'mime_type' => 'text/plain',
                    'size' => 1024,
                ],
            ],
            'body_html' => '<p>'.$this->faker->paragraph(2).'</p>',
            'body_text' => $this->faker->paragraph(3),
            'attempts' => 0,
            'queued_at' => now(),
            'dispatched_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'correlation_id' => Str::uuid()->toString(),
        ];
    }
}
