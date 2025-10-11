<?php

namespace App\Services;

use App\Exceptions\SmtpPermanentFailureException;
use App\Jobs\DispatchSmtpMessageJob;
use App\Mail\OutboundSmtpMailable;
use App\Models\Brand;
use App\Models\Message;
use App\Models\SmtpOutboundMessage;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SmtpOutboundMessageAuditLogger;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class SmtpOutboundMessageService
{
    public function __construct(
        private readonly MailFactory $mailFactory,
        private readonly SmtpOutboundMessageAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queue(array $data, User $actor, ?string $correlationId = null): SmtpOutboundMessage
    {
        $startedAt = microtime(true);
        $correlationId = $correlationId ?: (string) Str::uuid();

        /** @var Ticket $ticket */
        $ticket = Ticket::query()->findOrFail($data['ticket_id']);

        /** @var Message|null $message */
        $message = null;
        if (! empty($data['message_id'])) {
            /** @var Message $message */
            $message = Message::query()
                ->where('ticket_id', $ticket->getKey())
                ->findOrFail($data['message_id']);
        }

        $outbound = SmtpOutboundMessage::create([
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'message_id' => $message?->getKey(),
            'status' => SmtpOutboundMessage::STATUS_QUEUED,
            'mailer' => $data['mailer'] ?? 'smtp',
            'subject' => (string) $data['subject'],
            'from_email' => strtolower((string) $data['from_email']),
            'from_name' => $data['from_name'] ?? null,
            'to' => $this->sanitizeAddresses($data['to']),
            'cc' => $this->sanitizeAddresses($data['cc'] ?? []),
            'bcc' => $this->sanitizeAddresses($data['bcc'] ?? []),
            'reply_to' => $this->sanitizeAddresses($data['reply_to'] ?? []),
            'headers' => $this->sanitizeHeaders($data['headers'] ?? []),
            'attachments' => $this->sanitizeAttachments($data['attachments'] ?? []),
            'body_html' => $data['body_html'] ?? null,
            'body_text' => $data['body_text'] ?? null,
            'queued_at' => now(),
            'correlation_id' => $correlationId,
        ]);

        $this->auditLogger->queued($outbound, $actor, $startedAt, $correlationId);

        DispatchSmtpMessageJob::dispatch($outbound->getKey(), $correlationId);

        return $outbound->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SmtpOutboundMessage $message, array $data, User $actor, ?string $correlationId = null): SmtpOutboundMessage
    {
        if ($message->isTerminal()) {
            throw new \LogicException('Cannot update a dispatched SMTP message once delivery is finalized.');
        }

        $startedAt = microtime(true);
        $correlationId = $correlationId ?: (string) Str::uuid();

        if (array_key_exists('subject', $data)) {
            $message->subject = (string) $data['subject'];
        }

        if (array_key_exists('from_email', $data)) {
            $message->from_email = strtolower((string) $data['from_email']);
        }

        if (array_key_exists('from_name', $data)) {
            $message->from_name = $data['from_name'];
        }

        if (array_key_exists('to', $data)) {
            $message->to = $this->sanitizeAddresses($data['to'] ?? []);
        }

        if (array_key_exists('cc', $data)) {
            $message->cc = $this->sanitizeAddresses($data['cc'] ?? []);
        }

        if (array_key_exists('bcc', $data)) {
            $message->bcc = $this->sanitizeAddresses($data['bcc'] ?? []);
        }

        if (array_key_exists('reply_to', $data)) {
            $message->reply_to = $this->sanitizeAddresses($data['reply_to'] ?? []);
        }

        if (array_key_exists('headers', $data)) {
            $message->headers = $this->sanitizeHeaders($data['headers'] ?? []);
        }

        if (array_key_exists('attachments', $data)) {
            $message->attachments = $this->sanitizeAttachments($data['attachments'] ?? []);
        }

        if (array_key_exists('body_html', $data)) {
            $message->body_html = $data['body_html'];
        }

        if (array_key_exists('body_text', $data)) {
            $message->body_text = $data['body_text'];
        }

        if (array_key_exists('mailer', $data) && $data['mailer']) {
            $message->mailer = (string) $data['mailer'];
        }

        $dirty = Arr::except($message->getDirty(), ['updated_at']);

        if ($dirty === []) {
            return $message;
        }

        $message->save();

        $this->auditLogger->updated($message, $actor, $dirty, $startedAt, $correlationId);

        return $message->refresh();
    }

    public function delete(SmtpOutboundMessage $message, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlationId = $correlationId ?: (string) Str::uuid();

        $message->delete();

        $this->auditLogger->deleted($message, $actor, $startedAt, $correlationId);
    }

    public function send(int $messageId, string $correlationId): void
    {
        $startedAt = microtime(true);
        /** @var SmtpOutboundMessage $outbound */
        $outbound = SmtpOutboundMessage::withoutGlobalScopes()->findOrFail($messageId);
        $outbound->loadMissing(['tenant', 'brand']);

        /** @var Tenant|null $previousTenant */
        $previousTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        /** @var Brand|null $previousBrand */
        $previousBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        $tenant = $outbound->tenant;
        if ($tenant) {
            app()->instance('currentTenant', $tenant);
        }

        $brand = $outbound->brand;
        if ($brand) {
            app()->instance('currentBrand', $brand);
        } else {
            app()->forgetInstance('currentBrand');
        }

        $outbound->status = SmtpOutboundMessage::STATUS_SENDING;
        $outbound->dispatched_at = now();
        $outbound->attempts = $outbound->attempts + 1;
        $outbound->save();

        try {
            $mailer = $this->resolveMailer($outbound->mailer);
            $mailer->send(new OutboundSmtpMailable($outbound));

            $outbound->status = SmtpOutboundMessage::STATUS_SENT;
            $outbound->delivered_at = now();
            $outbound->last_error = null;
            $outbound->save();

            $this->auditLogger->sent($outbound, null, $startedAt, $correlationId);
        } catch (SmtpPermanentFailureException $exception) {
            $outbound->status = SmtpOutboundMessage::STATUS_FAILED;
            $outbound->failed_at = now();
            $outbound->last_error = $this->truncateError($exception->getMessage());
            $outbound->save();

            $this->auditLogger->failed($outbound, null, $startedAt, $correlationId, $this->errorDigest($exception), true);
        } catch (Throwable $exception) {
            $outbound->status = SmtpOutboundMessage::STATUS_RETRYING;
            $outbound->last_error = $this->truncateError($exception->getMessage());
            $outbound->save();

            $this->auditLogger->retrying($outbound, null, $startedAt, $correlationId, $this->errorDigest($exception));

            throw $exception;
        } finally {
            $this->restoreContext($previousTenant, $previousBrand);
        }
    }

    public function markFailed(int $messageId, Throwable $exception, string $correlationId): void
    {
        $outbound = SmtpOutboundMessage::withoutGlobalScopes()->find($messageId);

        if (! $outbound) {
            return;
        }

        $startedAt = microtime(true);

        $outbound->status = SmtpOutboundMessage::STATUS_FAILED;
        $outbound->failed_at = now();
        $outbound->last_error = $this->truncateError($exception->getMessage());
        $outbound->save();

        $this->auditLogger->failed($outbound, null, $startedAt, $correlationId, $this->errorDigest($exception), true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $addresses
     * @return array<int, array<string, string|null>>
     */
    protected function sanitizeAddresses(array $addresses): array
    {
        return array_values(array_filter(array_map(
            static function (array $address): ?array {
                if (empty($address['email'])) {
                    return null;
                }

                return [
                    'email' => strtolower((string) $address['email']),
                    'name' => isset($address['name']) ? (string) $address['name'] : null,
                ];
            },
            $addresses
        )));
    }

    /**
     * @param  array<string, string|int|null>  $headers
     * @return array<string, string>
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $sanitized[$key] = Str::limit((string) $value, 1024);
        }

        return $sanitized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeAttachments(array $attachments): array
    {
        $sanitized = array_map(
            static function (array $attachment): array {
                return [
                    'disk' => (string) ($attachment['disk'] ?? config('filesystems.default')),
                    'path' => (string) ($attachment['path'] ?? ''),
                    'name' => $attachment['name'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                ];
            },
            $attachments
        );

        return array_values(array_filter(
            $sanitized,
            static fn (array $attachment): bool => $attachment['path'] !== ''
        ));
    }

    protected function resolveMailer(string $mailer): Mailer
    {
        return $this->mailFactory->mailer($mailer);
    }

    protected function truncateError(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        return Str::limit($message, 1024);
    }

    protected function errorDigest(Throwable $exception): string
    {
        return hash('sha256', $exception::class.'|'.$exception->getMessage().'|'.$exception->getCode());
    }

    protected function restoreContext(?Tenant $tenant, ?Brand $brand): void
    {
        if ($tenant) {
            app()->instance('currentTenant', $tenant);
        } else {
            app()->forgetInstance('currentTenant');
        }

        if ($brand) {
            app()->instance('currentBrand', $brand);
        } else {
            app()->forgetInstance('currentBrand');
        }
    }
}
