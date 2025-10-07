<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketDepartment;
use App\Models\TicketTag;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketAuditLogger
{
    public function created(Ticket $ticket, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'workflow_state' => $ticket->workflow_state,
                'assignee_id' => $ticket->assignee_id,
                'contact_id' => $ticket->contact_id,
            ],
            'sensitive' => [
                'subject_digest' => $this->subjectDigest($ticket->subject),
                'metadata_keys' => array_keys($ticket->metadata ?? []),
            ],
        ];

        $this->persist($ticket, $actor, 'ticket.created', $payload);
        $this->logEvent('ticket.created', $ticket, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function updated(Ticket $ticket, ?User $actor, array $changes, array $original, float $startedAt): void
    {
        $diff = $this->diff($ticket, $changes, $original);

        if (empty($diff)) {
            return;
        }

        $this->persist($ticket, $actor, 'ticket.updated', $diff);
        $this->logEvent('ticket.updated', $ticket, $actor, $startedAt, $diff);
    }

    public function deleted(Ticket $ticket, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'assignee_id' => $ticket->assignee_id,
            ],
            'sensitive' => [
                'subject_digest' => $this->subjectDigest($ticket->subject),
            ],
        ];

        $this->persist($ticket, $actor, 'ticket.deleted', $payload);
        $this->logEvent('ticket.deleted', $ticket, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    public function redacted(Ticket $ticket, ?User $actor, array $aggregate, float $startedAt, string $correlationId): void
    {
        $payload = [
            'aggregate_snapshot' => $aggregate,
        ];

        $this->persist($ticket, $actor, 'ticket.redacted', $payload);

        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info('ticket.redacted', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'user_id' => $actor?->getKey(),
            'aggregate_keys' => array_keys($aggregate),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'context' => 'ticket_audit',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Ticket $ticket, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Ticket::class,
            'auditable_id' => $ticket->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    protected function diff(Ticket $ticket, array $changes, array $original): array
    {
        $diff = [];

        foreach ($changes as $field => $_value) {
            if ($field === 'metadata') {
                $diff['metadata_keys'] = [
                    'old' => array_keys((array) ($original['metadata'] ?? [])),
                    'new' => array_keys($ticket->metadata ?? []),
                ];
                continue;
            }

            if ($field === 'subject') {
                $diff['subject_digest'] = [
                    'old' => $this->subjectDigest($original['subject'] ?? null),
                    'new' => $this->subjectDigest($ticket->subject),
                ];
                continue;
            }

            if ($field === 'department_id') {
                $diff['department'] = [
                    'old' => $this->departmentName($original['department_id'] ?? null),
                    'new' => $ticket->departmentRelation?->name,
                ];
                continue;
            }

            if ($field === 'categories') {
                $diff['categories'] = [
                    'old' => $this->categoryNames($original['categories'] ?? []),
                    'new' => $ticket->categories->pluck('name')->all(),
                ];
                continue;
            }

            if ($field === 'tags') {
                $diff['tags'] = [
                    'old' => $this->tagNames($original['tags'] ?? []),
                    'new' => $ticket->tags->pluck('name')->all(),
                ];
                continue;
            }

            $diff[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $ticket->{$field},
            ];
        }

        return $diff;
    }

    protected function departmentName(?int $departmentId): ?string
    {
        if (! $departmentId) {
            return null;
        }

        return TicketDepartment::query()->find($departmentId)?->name;
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    protected function categoryNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return TicketCategory::query()->whereIn('id', $ids)->pluck('name')->all();
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    protected function tagNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return TicketTag::query()->whereIn('id', $ids)->pluck('name')->all();
    }

    protected function subjectDigest(?string $value): string
    {
        return hash('sha256', (string) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Ticket $ticket, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'assignee_id' => $ticket->assignee_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'ticket_audit',
        ]);
    }
}
