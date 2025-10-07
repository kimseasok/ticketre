<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketSubmission>
 */
class TicketSubmissionFactory extends Factory
{
    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;
        $contactId = $this->attributes['contact_id'] ?? Contact::factory()->create([
            'tenant_id' => $tenantId,
        ])->id;

        $ticket = $this->attributes['ticket_id'] ?? Ticket::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'contact_id' => $contactId,
            'channel' => Ticket::CHANNEL_PORTAL,
        ]);

        $message = $this->attributes['message_id'] ?? Message::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'author_role' => Message::ROLE_CONTACT,
            'visibility' => Message::VISIBILITY_PUBLIC,
        ]);

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'ticket_id' => $ticket->id,
            'contact_id' => $contactId,
            'message_id' => $message->id,
            'channel' => TicketSubmission::CHANNEL_PORTAL,
            'status' => TicketSubmission::STATUS_ACCEPTED,
            'subject' => $this->faker->sentence(6),
            'message' => $this->faker->paragraph(),
            'tags' => ['support'],
            'metadata' => [
                'ip_hash' => hash('sha256', $this->faker->ipv4()),
            ],
            'correlation_id' => (string) Str::uuid(),
            'submitted_at' => now(),
        ];
    }
}
