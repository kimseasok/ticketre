<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function create(Ticket $ticket, array $payload, User $user): Message
    {
        $startedAt = microtime(true);

        $message = Message::create(array_merge($payload, [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'user_id' => $user->getKey(),
        ]));

        $this->recordAudit($user, 'message.created', $message, [
            'visibility' => $message->visibility,
            'author_role' => $message->author_role,
            'body' => '[REDACTED]',
        ]);

        $this->logEvent('message.created', $message, $user, $startedAt);

        return $message->refresh();
    }

    public function update(Message $message, array $payload, User $user): Message
    {
        $startedAt = microtime(true);

        $original = Arr::only($message->getOriginal(), ['visibility', 'author_role']);

        $message->fill($payload);
        $message->save();

        $changes = Arr::except($message->getChanges(), ['updated_at']);
        if (! empty($changes)) {
            $this->recordAudit($user, 'message.updated', $message, array_merge($changes, [
                'body' => '[REDACTED]',
                'original' => $original,
            ]));
        }

        $this->logEvent('message.updated', $message, $user, $startedAt);

        return $message->refresh();
    }

    public function delete(Message $message, User $user): void
    {
        $startedAt = microtime(true);
        $message->delete();

        $this->recordAudit($user, 'message.deleted', $message, [
            'visibility' => $message->visibility,
            'author_role' => $message->author_role,
        ]);

        $this->logEvent('message.deleted', $message, $user, $startedAt);
    }

    protected function recordAudit(User $user, string $action, Message $message, array $changes): void
    {
        AuditLog::create([
            'tenant_id' => $message->tenant_id,
            'brand_id' => $message->brand_id,
            'user_id' => $user->getKey(),
            'action' => $action,
            'auditable_type' => Message::class,
            'auditable_id' => $message->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function logEvent(string $action, Message $message, User $user, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'message_id' => $message->getKey(),
            'ticket_id' => $message->ticket_id,
            'tenant_id' => $message->tenant_id,
            'brand_id' => $message->brand_id,
            'visibility' => $message->visibility,
            'author_role' => $message->author_role,
            'body_digest' => hash('sha256', (string) $message->body),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $user->getKey(),
            'context' => 'ticket_message',
        ]);
    }
}
