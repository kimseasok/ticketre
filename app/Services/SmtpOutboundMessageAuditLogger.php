<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SmtpOutboundMessage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SmtpOutboundMessageAuditLogger
{
    public function queued(SmtpOutboundMessage $message, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = $this->snapshot($message);

        $this->persist($message, $actor, 'smtp_outbound.queued', $payload);
        $this->logEvent('smtp_outbound.queued', $message, $actor->getKey(), $payload, $startedAt, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(SmtpOutboundMessage $message, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $payload = $this->redactChanges($changes);

        $this->persist($message, $actor, 'smtp_outbound.updated', $payload);
        $this->logEvent('smtp_outbound.updated', $message, $actor->getKey(), $payload, $startedAt, $correlationId);
    }

    public function deleted(SmtpOutboundMessage $message, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = $this->snapshot($message);

        $this->persist($message, $actor, 'smtp_outbound.deleted', $payload);
        $this->logEvent('smtp_outbound.deleted', $message, $actor->getKey(), $payload, $startedAt, $correlationId);
    }

    public function sent(SmtpOutboundMessage $message, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = array_merge($this->snapshot($message), [
            'status' => SmtpOutboundMessage::STATUS_SENT,
        ]);

        $this->persist($message, $actor, 'smtp_outbound.sent', $payload);
        $this->logEvent('smtp_outbound.sent', $message, $actor?->getKey(), $payload, $startedAt, $correlationId);
    }

    public function retrying(SmtpOutboundMessage $message, ?User $actor, float $startedAt, string $correlationId, string $errorDigest): void
    {
        $payload = array_merge($this->snapshot($message), [
            'status' => SmtpOutboundMessage::STATUS_RETRYING,
            'error_digest' => $errorDigest,
        ]);

        $this->persist($message, $actor, 'smtp_outbound.retrying', $payload);
        $this->logEvent('smtp_outbound.retrying', $message, $actor?->getKey(), $payload, $startedAt, $correlationId);
    }

    public function failed(SmtpOutboundMessage $message, ?User $actor, float $startedAt, string $correlationId, string $errorDigest, bool $permanent): void
    {
        $payload = array_merge($this->snapshot($message), [
            'status' => SmtpOutboundMessage::STATUS_FAILED,
            'error_digest' => $errorDigest,
            'permanent' => $permanent,
        ]);

        $this->persist($message, $actor, 'smtp_outbound.failed', $payload);
        $this->logEvent('smtp_outbound.failed', $message, $actor?->getKey(), $payload, $startedAt, $correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(SmtpOutboundMessage $message): array
    {
        return [
            'status' => $message->status,
            'subject_digest' => hash('sha256', (string) $message->subject),
            'to' => $message->recipientDigests(),
            'attachment_count' => count($message->attachments ?? []),
            'attachment_digests' => $this->attachmentDigests($message),
            'attempts' => $message->attempts,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function redactChanges(array $changes): array
    {
        $payload = [];

        foreach ($changes as $key => $value) {
            if (in_array($key, ['to', 'cc', 'bcc', 'reply_to'], true) && is_array($value)) {
                $payload[$key] = array_map(
                    static fn (array $entry): string => hash('sha256', strtolower((string) ($entry['email'] ?? ''))),
                    $value
                );
                continue;
            }

            if ($key === 'subject') {
                $payload['subject_digest'] = hash('sha256', (string) $value);
                continue;
            }

            if ($key === 'attachments' && is_array($value)) {
                $payload['attachment_digests'] = array_map(
                    fn (array $attachment): string => $this->attachmentHash($attachment),
                    $value
                );
                $payload['attachment_count'] = count($value);
                continue;
            }

            if (in_array($key, ['body_html', 'body_text'], true)) {
                $payload[$key.'_digest'] = hash('sha256', (string) $value);
                continue;
            }

            $payload[$key] = is_scalar($value) ? $value : Arr::wrap($value);
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    protected function attachmentDigests(SmtpOutboundMessage $message): array
    {
        $attachments = $message->attachments ?? [];

        return array_map(fn (array $attachment): string => $this->attachmentHash($attachment), $attachments);
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    protected function attachmentHash(array $attachment): string
    {
        $seed = implode('|', [
            (string) ($attachment['disk'] ?? ''),
            (string) ($attachment['path'] ?? ''),
            (string) ($attachment['name'] ?? ''),
        ]);

        return hash('sha256', $seed);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(SmtpOutboundMessage $message, ?User $actor, string $action, array $payload): void
    {
        $ipAddress = app()->bound('request') ? request()->ip() : null;

        AuditLog::create([
            'tenant_id' => $message->tenant_id,
            'brand_id' => $message->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => SmtpOutboundMessage::class,
            'auditable_id' => $message->getKey(),
            'changes' => $payload,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, SmtpOutboundMessage $message, ?int $actorId, array $payload, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'smtp_outbound_id' => $message->getKey(),
            'tenant_id' => $message->tenant_id,
            'brand_id' => $message->brand_id,
            'ticket_id' => $message->ticket_id,
            'message_id' => $message->message_id,
            'status' => $message->status,
            'attempts' => $message->attempts,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actorId,
            'correlation_id' => $correlationId,
            'context' => 'smtp_outbound',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
