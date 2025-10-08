<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMerge;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketMergeService
{
    public function __construct(
        private readonly TicketMergeAuditLogger $auditLogger,
        private readonly TicketAuditLogger $ticketAuditLogger,
        private readonly TicketLifecycleBroadcaster $broadcaster,
        private readonly TicketRelationshipService $relationshipService,
        private readonly DatabaseManager $database,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data, User $actor, ?string $correlationId = null): TicketMerge
    {
        $startedAt = microtime(true);
        $correlation = $this->sanitizeCorrelationId($correlationId ?? $data['correlation_id'] ?? null);

        $primary = $this->resolveTicket((int) $data['primary_ticket_id'], $actor);
        $secondary = $this->resolveTicket((int) $data['secondary_ticket_id'], $actor);

        $this->guardTickets($primary, $secondary, $actor);

        $merge = TicketMerge::create([
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
            'initiated_by' => $actor->getKey(),
            'status' => TicketMerge::STATUS_PROCESSING,
            'correlation_id' => $correlation,
        ]);

        $this->auditLogger->created($merge, $actor, $startedAt);

        try {
            $summary = $this->database->transaction(function () use ($merge, $primary, $secondary, $actor, $correlation, $startedAt) {
                $summary = $this->processMerge($primary, $secondary, $actor, $correlation, $startedAt);

                $merge->forceFill([
                    'status' => TicketMerge::STATUS_COMPLETED,
                    'summary' => $summary,
                    'completed_at' => now(),
                ])->save();

                return $summary;
            });
        } catch (Throwable $exception) {
            $failureReason = Str::limit($exception->getMessage(), 255, '');

            $merge->forceFill([
                'status' => TicketMerge::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => $failureReason,
            ])->save();

            $this->auditLogger->failed($merge->fresh(), $actor, $failureReason, $startedAt);

            Log::channel(config('logging.default'))->error('ticket.merge.failed', [
                'ticket_merge_id' => $merge->getKey(),
                'primary_ticket_id' => $primary->getKey(),
                'secondary_ticket_id' => $secondary->getKey(),
                'tenant_id' => $primary->tenant_id,
                'brand_id' => $primary->brand_id,
                'user_id' => $actor->getKey(),
                'correlation_id' => $correlation,
                'context' => 'ticket_merge',
                'failure_reason_hash' => hash('sha256', $exception->getMessage()),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);

            throw $exception;
        }

        $merge->refresh()->load(['primaryTicket', 'secondaryTicket', 'initiator']);

        $this->auditLogger->completed($merge, $actor, $summary, $startedAt);

        Log::channel(config('logging.default'))->info('ticket.merge.completed', [
            'ticket_merge_id' => $merge->getKey(),
            'primary_ticket_id' => $merge->primary_ticket_id,
            'secondary_ticket_id' => $merge->secondary_ticket_id,
            'tenant_id' => $merge->tenant_id,
            'brand_id' => $merge->brand_id,
            'user_id' => $actor->getKey(),
            'correlation_id' => $merge->correlation_id,
            'context' => 'ticket_merge',
            'summary' => $summary,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $merge;
    }

    protected function processMerge(Ticket $primary, Ticket $secondary, User $actor, string $correlationId, float $startedAt): array
    {
        $now = now();

        $primaryMetadataBefore = (array) $primary->metadata;
        $secondaryMetadata = (array) $secondary->metadata;
        $secondaryMetadataBefore = (array) $secondary->getOriginal('metadata');

        $primaryCustomFieldsBefore = $this->normalizeCustomFields($primary->custom_fields ?? []);
        $secondaryCustomFields = $this->normalizeCustomFields($secondary->custom_fields ?? []);

        $messagesMigrated = Message::query()
            ->where('tenant_id', $primary->tenant_id)
            ->where('ticket_id', $secondary->getKey())
            ->update([
                'ticket_id' => $primary->getKey(),
                'brand_id' => $primary->brand_id,
                'updated_at' => $now,
            ]);

        $eventsMigrated = TicketEvent::query()
            ->where('tenant_id', $primary->tenant_id)
            ->where('ticket_id', $secondary->getKey())
            ->update([
                'ticket_id' => $primary->getKey(),
                'brand_id' => $primary->brand_id,
                'updated_at' => $now,
            ]);

        $attachmentsMigrated = Attachment::query()
            ->where('tenant_id', $primary->tenant_id)
            ->where('attachable_type', Ticket::class)
            ->where('attachable_id', $secondary->getKey())
            ->update([
                'attachable_id' => $primary->getKey(),
                'updated_at' => $now,
            ]);

        $mergedMetadata = $this->mergeMetadata($primaryMetadataBefore, $secondaryMetadata);
        $mergedCustomFields = $this->mergeCustomFields($primaryCustomFieldsBefore, $secondaryCustomFields);

        $mergedMetadata['merged_ticket_ids'] = array_values(array_unique(array_merge(
            (array) ($primaryMetadataBefore['merged_ticket_ids'] ?? []),
            [$secondary->getKey()]
        )));

        $primary->forceFill([
            'metadata' => $mergedMetadata,
            'custom_fields' => array_values($mergedCustomFields),
        ]);
        $primary->save();

        $secondaryOriginalStatus = $secondary->getOriginal('status');
        $secondaryOriginalWorkflow = $secondary->getOriginal('workflow_state');
        $secondaryOriginalAssignee = $secondary->getOriginal('assignee_id');

        $secondaryMetadata['merged_into_ticket_id'] = $primary->getKey();
        $secondaryMetadata['merge_correlation_id'] = $correlationId;
        $secondaryMetadata['merge_completed_at'] = $now->toISOString();

        $secondary->forceFill([
            'status' => 'closed',
            'workflow_state' => 'merged',
            'metadata' => $secondaryMetadata,
            'assignee_id' => $primary->assignee_id,
        ]);
        $secondary->save();

        $primaryChanges = [
            'metadata' => $primary->metadata,
            'custom_fields' => $primary->custom_fields,
        ];
        $primaryOriginal = [
            'metadata' => $primaryMetadataBefore,
            'custom_fields' => array_values($primaryCustomFieldsBefore),
        ];

        $secondaryChanges = [
            'status' => $secondary->status,
            'workflow_state' => $secondary->workflow_state,
            'metadata' => $secondary->metadata,
            'assignee_id' => $secondary->assignee_id,
        ];
        $secondaryOriginal = [
            'status' => $secondaryOriginalStatus,
            'workflow_state' => $secondaryOriginalWorkflow,
            'metadata' => $secondaryMetadataBefore,
            'assignee_id' => $secondaryOriginalAssignee,
        ];

        $this->ticketAuditLogger->updated($primary->fresh(), $actor, $primaryChanges, $primaryOriginal, $startedAt);
        $this->ticketAuditLogger->updated($secondary->fresh(), $actor, $secondaryChanges, $secondaryOriginal, $startedAt);

        $this->relationshipService->create([
            'primary_ticket_id' => $primary->getKey(),
            'related_ticket_id' => $secondary->getKey(),
            'relationship_type' => TicketRelationship::TYPE_MERGE,
            'context' => [
                'messages_migrated' => $messagesMigrated,
                'events_migrated' => $eventsMigrated,
                'attachments_migrated' => $attachmentsMigrated,
            ],
            'correlation_id' => $correlationId,
        ], $actor, $correlationId);

        $this->broadcaster->record($primary->fresh(), TicketEvent::TYPE_MERGED, [
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
            'messages_migrated' => $messagesMigrated,
            'events_migrated' => $eventsMigrated,
        ], $actor);

        return [
            'messages_migrated' => $messagesMigrated,
            'events_migrated' => $eventsMigrated,
            'attachments_migrated' => $attachmentsMigrated,
            'metadata_keys_merged' => array_values(array_diff(array_keys($secondaryMetadata), array_keys($primaryMetadataBefore))),
            'custom_field_keys_merged' => array_values(array_diff(array_keys($secondaryCustomFields), array_keys($primaryCustomFieldsBefore))),
        ];
    }

    protected function guardTickets(Ticket $primary, Ticket $secondary, User $actor): void
    {
        if ($primary->getKey() === $secondary->getKey()) {
            throw ValidationException::withMessages([
                'secondary_ticket_id' => 'Cannot merge a ticket into itself.',
            ]);
        }

        if ($primary->tenant_id !== $actor->tenant_id || $secondary->tenant_id !== $actor->tenant_id) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Tickets must belong to the actor tenant for merging.',
            ]);
        }

        if ($primary->brand_id !== $secondary->brand_id) {
            throw ValidationException::withMessages([
                'secondary_ticket_id' => 'Tickets must belong to the same brand to merge.',
            ]);
        }
    }

    protected function resolveTicket(int $ticketId, User $actor): Ticket
    {
        $ticket = Ticket::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($ticketId)
            ->first();

        if (! $ticket) {
            throw ValidationException::withMessages([
                'ticket_id' => 'Ticket not found for merge operation.',
            ]);
        }

        return $ticket;
    }

    protected function sanitizeCorrelationId(?string $value): string
    {
        if (! $value) {
            return (string) Str::uuid();
        }

        return Str::limit($value, 64, '');
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    protected function mergeMetadata(array $primary, array $secondary): array
    {
        return array_replace_recursive($secondary, $primary);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeCustomFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $key = (string) Arr::get($field, 'key', Str::uuid());
            $normalized[$key] = [
                'key' => $key,
                'type' => Arr::get($field, 'type', 'string'),
                'value' => Arr::get($field, 'value'),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $primary
     * @param  array<string, array<string, mixed>>  $secondary
     * @return array<string, array<string, mixed>>
     */
    protected function mergeCustomFields(array $primary, array $secondary): array
    {
        return $secondary + $primary;
    }
}
