<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketSubmission;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PortalTicketSubmissionService
{
    public function __construct(
        private readonly TicketAuditLogger $auditLogger,
        private readonly TicketLifecycleBroadcaster $broadcaster,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $attachments
     */
    public function submit(array $payload, array $attachments = [], ?string $correlationId = null): TicketSubmission
    {
        $tenant = $this->resolveTenant();
        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        $correlationId = Str::limit($correlationId ?: (request()?->header('X-Correlation-ID') ?? (string) Str::uuid()), 64, '');
        $startedAt = microtime(true);

        $sanitizedTags = array_values(array_filter(array_map(
            static fn (string $tag): string => Str::of($tag)->trim()->limit(50, '')->__toString(),
            Arr::wrap($payload['tags'] ?? [])
        ), static fn (string $tag): bool => $tag !== ''));

        $ipHash = hash('sha256', (string) ($payload['ip_address'] ?? request()?->ip() ?? ''));
        $userAgent = Str::of((string) ($payload['user_agent'] ?? request()?->userAgent() ?? ''))->limit(255, '')->__toString();

        try {
            $submission = DB::transaction(function () use ($payload, $attachments, $tenant, $brand, $sanitizedTags, $correlationId, $ipHash, $userAgent, $startedAt) {
                $contact = $this->findOrCreateContact($tenant, $payload['email'], $payload['name'] ?? null);

                $ticket = Ticket::create([
                    'tenant_id' => $tenant->getKey(),
                    'brand_id' => $brand?->getKey(),
                    'contact_id' => $contact->getKey(),
                    'subject' => $payload['subject'],
                    'status' => 'open',
                    'priority' => 'medium',
                    'channel' => Ticket::CHANNEL_PORTAL,
                    'metadata' => array_filter([
                        'channel_tags' => $sanitizedTags,
                        'portal_submission' => true,
                        'portal_ip_hash' => $ipHash,
                        'portal_user_agent' => $userAgent ?: null,
                    ], static fn ($value) => $value !== null && $value !== []),
                ]);

                $message = Message::create([
                    'tenant_id' => $tenant->getKey(),
                    'brand_id' => $brand?->getKey(),
                    'ticket_id' => $ticket->getKey(),
                    'user_id' => null,
                    'author_role' => Message::ROLE_CONTACT,
                    'visibility' => Message::VISIBILITY_PUBLIC,
                    'body' => $payload['message'],
                    'sent_at' => now(),
                ]);

                $submission = TicketSubmission::create([
                    'tenant_id' => $tenant->getKey(),
                    'brand_id' => $brand?->getKey(),
                    'ticket_id' => $ticket->getKey(),
                    'contact_id' => $contact->getKey(),
                    'message_id' => $message->getKey(),
                    'channel' => TicketSubmission::CHANNEL_PORTAL,
                    'status' => TicketSubmission::STATUS_ACCEPTED,
                    'subject' => $payload['subject'],
                    'message' => $payload['message'],
                    'tags' => $sanitizedTags,
                    'metadata' => array_filter([
                        'ip_hash' => $ipHash,
                        'user_agent' => $userAgent ?: null,
                        'filename_digests' => $this->attachmentDigests($attachments),
                    ], static fn ($value) => $value !== null && $value !== []),
                    'correlation_id' => $correlationId,
                    'submitted_at' => now(),
                ]);

                $this->persistAttachments($attachments, $submission, $message);

                $this->auditLogger->created($ticket, null, $startedAt);

                $this->broadcaster->record($ticket->fresh(), TicketEvent::TYPE_CREATED, [
                    'channel' => Ticket::CHANNEL_PORTAL,
                    'submission_id' => $submission->getKey(),
                ], null, TicketEvent::VISIBILITY_PUBLIC);

                Log::channel(config('logging.default'))->info('ticket.portal.submitted', [
                    'ticket_id' => $ticket->getKey(),
                    'tenant_id' => $tenant->getKey(),
                    'brand_id' => $brand?->getKey(),
                    'contact_id' => $contact->getKey(),
                    'submission_id' => $submission->getKey(),
                    'channel' => Ticket::CHANNEL_PORTAL,
                    'tags_count' => count($sanitizedTags),
                    'attachments' => count($attachments),
                    'email_hash' => hash('sha256', Str::lower($payload['email'])),
                    'correlation_id' => $correlationId,
                    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'context' => 'portal_ticket',
                ]);

                return $submission->fresh(['ticket', 'contact', 'messageRecord']);
            }, 3);
        } catch (Throwable $e) {
            Log::channel(config('logging.default'))->error('ticket.portal.failed', [
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId,
                'context' => 'portal_ticket',
            ]);

            throw $e;
        }

        return $submission;
    }

    protected function resolveTenant(): Tenant
    {
        if (! app()->bound('currentTenant') || ! app('currentTenant')) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_TENANT_NOT_FOUND',
                    'message' => 'Tenant could not be resolved.',
                ],
            ], 404));
        }

        return app('currentTenant');
    }

    protected function findOrCreateContact(Tenant $tenant, string $email, ?string $name = null): Contact
    {
        $contact = Contact::query()->firstOrNew([
            'email' => $email,
            'tenant_id' => $tenant->getKey(),
        ]);

        if (! $contact->exists) {
            $contact->name = $name ?: 'Portal Contact';
            $contact->metadata = ['source' => 'portal'];
        } elseif ($name && $contact->name !== $name) {
            $contact->name = $name;
        }

        $metadata = (array) ($contact->metadata ?? []);
        $metadata['source'] = 'portal';
        $contact->metadata = $metadata;
        $contact->save();

        return $contact;
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     * @return array<int, string>
     */
    protected function attachmentDigests(array $attachments): array
    {
        return array_map(static fn (UploadedFile $file): string => hash('sha256', $file->getClientOriginalName()), $attachments);
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    protected function persistAttachments(array $attachments, TicketSubmission $submission, Message $message): void
    {
        if ($attachments === []) {
            return;
        }

        $disk = config('filesystems.default', 'local');
        $directory = sprintf('portal/%d/%d', $submission->tenant_id, $submission->ticket_id);

        foreach ($attachments as $file) {
            $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $filename, $disk);

            $data = [
                'tenant_id' => $submission->tenant_id,
                'disk' => $disk,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            ];

            $submission->attachments()->create($data);
            $message->attachments()->create($data);
        }
    }
}
