<?php

namespace App\Services;

use App\Jobs\ProcessTicketDeletionRequestJob;
use App\Models\Attachment;
use App\Models\Brand;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketDeletionService
{
    public const DEFAULT_HOLD_HOURS = 48;

    public function __construct(
        private readonly TicketAuditLogger $auditLogger,
        private readonly DatabaseManager $database
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(array $data, User $actor, ?string $correlationId = null): TicketDeletionRequest
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $actor->tenant_id)
            ->findOrFail($data['ticket_id']);

        if ($ticket->tenant_id !== $actor->tenant_id) {
            throw ValidationException::withMessages([
                'ticket_id' => 'The selected ticket does not belong to your tenant.',
            ]);
        }

        $existing = TicketDeletionRequest::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('ticket_id', $ticket->getKey())
            ->whereNotIn('status', [
                TicketDeletionRequest::STATUS_COMPLETED,
                TicketDeletionRequest::STATUS_CANCELLED,
                TicketDeletionRequest::STATUS_FAILED,
            ])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'ticket_id' => 'An active deletion request already exists for this ticket.',
            ]);
        }

        $metadata = Arr::wrap($ticket->metadata);
        if (Arr::get($metadata, 'redacted') === true) {
            throw ValidationException::withMessages([
                'ticket_id' => 'This ticket has already been redacted.',
            ]);
        }

        $correlation = $correlationId ?: (string) Str::uuid();
        $startedAt = microtime(true);

        $request = TicketDeletionRequest::create([
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $data['brand_id'] ?? $actor->brand_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $actor->getKey(),
            'status' => TicketDeletionRequest::STATUS_PENDING,
            'reason' => $data['reason'],
            'correlation_id' => $correlation,
        ]);

        Log::channel(config('logging.default'))->info('ticket.deletion.requested', [
            'request_id' => $request->getKey(),
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $request->brand_id,
            'correlation_id' => $correlation,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $request;
    }

    public function approve(TicketDeletionRequest $request, User $actor, int $holdHours = self::DEFAULT_HOLD_HOURS): TicketDeletionRequest
    {
        if ($request->status !== TicketDeletionRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $holdHours = max(0, $holdHours);
        $holdExpiresAt = now()->addHours($holdHours);

        $request->forceFill([
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
            'hold_expires_at' => $holdExpiresAt,
            'status' => TicketDeletionRequest::STATUS_APPROVED,
        ])->save();

        $job = ProcessTicketDeletionRequestJob::dispatch($request->getKey());

        if ($holdHours > 0) {
            $job->delay($holdExpiresAt);
        }

        Log::channel(config('logging.default'))->info('ticket.deletion.approved', [
            'request_id' => $request->getKey(),
            'ticket_id' => $request->ticket_id,
            'tenant_id' => $request->tenant_id,
            'brand_id' => $request->brand_id,
            'approved_by' => $actor->getKey(),
            'hold_expires_at' => $holdExpiresAt->toIso8601String(),
            'correlation_id' => $request->correlation_id,
        ]);

        return $request;
    }

    public function cancel(TicketDeletionRequest $request, User $actor): TicketDeletionRequest
    {
        if (! in_array($request->status, [TicketDeletionRequest::STATUS_PENDING, TicketDeletionRequest::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only pending or approved requests can be cancelled.',
            ]);
        }

        if ($request->status === TicketDeletionRequest::STATUS_APPROVED && $request->hold_expires_at && $request->hold_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'The hold period has expired and the request can no longer be cancelled.',
            ]);
        }

        $request->forceFill([
            'status' => TicketDeletionRequest::STATUS_CANCELLED,
            'cancelled_by' => $actor->getKey(),
            'cancelled_at' => now(),
        ])->save();

        Log::channel(config('logging.default'))->info('ticket.deletion.cancelled', [
            'request_id' => $request->getKey(),
            'ticket_id' => $request->ticket_id,
            'tenant_id' => $request->tenant_id,
            'brand_id' => $request->brand_id,
            'cancelled_by' => $actor->getKey(),
            'correlation_id' => $request->correlation_id,
        ]);

        return $request;
    }

    public function process(int $requestId): TicketDeletionRequest
    {
        /** @var TicketDeletionRequest $request */
        $request = TicketDeletionRequest::query()->findOrFail($requestId);

        if (in_array($request->status, [TicketDeletionRequest::STATUS_COMPLETED, TicketDeletionRequest::STATUS_CANCELLED], true)) {
            return $request;
        }

        if ($request->status !== TicketDeletionRequest::STATUS_APPROVED) {
            return $request;
        }

        if ($request->hold_expires_at && $request->hold_expires_at->isFuture()) {
            return $request;
        }

        $startedAt = microtime(true);

        $previousTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $previousBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        $this->database->transaction(function () use ($request, $previousTenant, $previousBrand): void {
            $request->refresh();

            if ($request->status !== TicketDeletionRequest::STATUS_APPROVED) {
                return;
            }

            if ($request->hold_expires_at && $request->hold_expires_at->isFuture()) {
                return;
            }

            $request->status = TicketDeletionRequest::STATUS_PROCESSING;
            $request->save();

            $ticket = Ticket::withoutGlobalScopes()
                ->where('tenant_id', $request->tenant_id)
                ->lockForUpdate()
                ->findOrFail($request->ticket_id);

            $messageQuery = Message::withoutGlobalScopes()
                ->where('tenant_id', $request->tenant_id)
                ->where('ticket_id', $ticket->getKey());

            $messageCount = (clone $messageQuery)->count();
            $publicCount = (clone $messageQuery)->where('visibility', Message::VISIBILITY_PUBLIC)->count();
            $internalCount = (clone $messageQuery)->where('visibility', Message::VISIBILITY_INTERNAL)->count();
            $firstMessageAt = (clone $messageQuery)->min('sent_at');
            $lastMessageAt = (clone $messageQuery)->max('sent_at');
            $messageIds = (clone $messageQuery)->pluck('id')->all();

            $attachmentQuery = Attachment::withoutGlobalScopes()
                ->where('tenant_id', $request->tenant_id)
                ->where(function ($builder) use ($ticket, $messageIds) {
                    $builder->where(function ($query) use ($ticket) {
                        $query->where('attachable_type', Ticket::class)
                            ->where('attachable_id', $ticket->getKey());
                    });

                    if (! empty($messageIds)) {
                        $builder->orWhere(function ($query) use ($messageIds) {
                            $query->where('attachable_type', Message::class)
                                ->whereIn('attachable_id', $messageIds);
                        });
                    }
                });

            $attachmentCount = (clone $attachmentQuery)->count();
            $attachmentBytes = (clone $attachmentQuery)->sum('size');

            $firstAt = $firstMessageAt ? Carbon::parse($firstMessageAt)->toIso8601String() : null;
            $lastAt = $lastMessageAt ? Carbon::parse($lastMessageAt)->toIso8601String() : null;

            $aggregate = [
                'ticket' => [
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'workflow_state' => $ticket->workflow_state,
                    'subject_digest' => hash('sha256', (string) $ticket->subject),
                    'created_at' => optional($ticket->created_at)->toIso8601String(),
                    'updated_at' => optional($ticket->updated_at)->toIso8601String(),
                ],
                'messages' => [
                    'total' => $messageCount,
                    'public' => $publicCount,
                    'internal' => $internalCount,
                    'first_at' => $firstAt,
                    'last_at' => $lastAt,
                ],
                'attachments' => [
                    'total' => $attachmentCount,
                    'bytes' => (int) $attachmentBytes,
                ],
            ];

            $request->aggregate_snapshot = $aggregate;
            $request->save();

            $metadata = Arr::wrap($ticket->metadata);
            $metadata['redacted'] = true;
            $metadata['redacted_at'] = now()->toIso8601String();
            $metadata['ticket_deletion_request_id'] = $request->getKey();

            $ticket->fill([
                'subject' => sprintf('Redacted Ticket %s', Str::upper(Str::random(8))),
                'contact_id' => null,
                'company_id' => null,
                'assignee_id' => null,
                'metadata' => $metadata,
            ]);
            $ticket->save();
            $ticket->delete();

            $messageQuery->cursor()->each(function (Message $message): void {
                $message->body = '[REDACTED]';
                $message->save();

                Attachment::withoutGlobalScopes()
                    ->where('tenant_id', $message->tenant_id)
                    ->where('attachable_type', Message::class)
                    ->where('attachable_id', $message->getKey())
                    ->cursor()
                    ->each(fn (Attachment $attachment) => $attachment->delete());

                $message->delete();
            });

            Attachment::withoutGlobalScopes()
                ->where('tenant_id', $ticket->tenant_id)
                ->where('attachable_type', Ticket::class)
                ->where('attachable_id', $ticket->getKey())
                ->cursor()
                ->each(fn (Attachment $attachment) => $attachment->delete());

            $tenant = Tenant::query()->find($request->tenant_id);
            if ($tenant) {
                app()->instance('currentTenant', $tenant);
            }

            if ($request->brand_id) {
                $brand = Brand::withoutGlobalScopes()
                    ->where('tenant_id', $request->tenant_id)
                    ->find($request->brand_id);

                if ($brand) {
                    app()->instance('currentBrand', $brand);
                }
            }

            $auditStartedAt = microtime(true);

            $this->auditLogger->redacted(
                $ticket->fresh(),
                $request->approver,
                $aggregate,
                $auditStartedAt,
                $request->correlation_id
            );

            if ($previousTenant) {
                app()->instance('currentTenant', $previousTenant);
            } else {
                app()->forgetInstance('currentTenant');
            }

            if ($previousBrand) {
                app()->instance('currentBrand', $previousBrand);
            } else {
                app()->forgetInstance('currentBrand');
            }
        });

        $request->refresh();

        if ($request->status === TicketDeletionRequest::STATUS_PROCESSING) {
            $request->status = TicketDeletionRequest::STATUS_COMPLETED;
            $request->processed_at = now();
            $request->failed_at = null;
            $request->failure_reason = null;
            $request->save();
        }

        if ($request->status === TicketDeletionRequest::STATUS_COMPLETED) {
            Log::channel(config('logging.default'))->info('ticket.deletion.completed', [
                'request_id' => $request->getKey(),
                'ticket_id' => $request->ticket_id,
                'tenant_id' => $request->tenant_id,
                'brand_id' => $request->brand_id,
                'correlation_id' => $request->correlation_id,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
        }

        return $request;
    }

    public function markFailed(int $requestId, Throwable $exception): void
    {
        $request = TicketDeletionRequest::query()->find($requestId);

        if (! $request) {
            return;
        }

        $request->status = TicketDeletionRequest::STATUS_FAILED;
        $request->failed_at = now();
        $request->failure_reason = Str::limit($exception->getMessage(), 500);
        $request->save();

        Log::channel(config('logging.default'))->error('ticket.deletion.failed', [
            'request_id' => $request->getKey(),
            'ticket_id' => $request->ticket_id,
            'tenant_id' => $request->tenant_id,
            'brand_id' => $request->brand_id,
            'correlation_id' => $request->correlation_id,
            'exception' => class_basename($exception),
            'message_hash' => hash('sha256', $exception->getMessage()),
        ]);
    }
}
