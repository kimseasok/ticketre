<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketDepartment;
use App\Models\TicketEvent;
use App\Models\TicketTag;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(
        private readonly TicketLifecycleBroadcaster $broadcaster,
        private readonly TicketAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Ticket
    {
        $startedAt = microtime(true);
        $correlationId = $this->resolveCorrelationId();

        [$payload, $categoryIds, $tagIds] = $this->splitAssociations($data);
        $this->hydrateDepartmentName($payload);

        $ticket = DB::transaction(function () use ($payload, $categoryIds, $tagIds, $actor, $startedAt) {
            $ticket = Ticket::create($payload);

            if ($categoryIds !== null) {
                $ticket->categories()->sync($categoryIds);
            }

            if ($tagIds !== null) {
                $ticket->tags()->sync($tagIds);
            }

            $ticket->load(['assignee', 'categories', 'tags', 'departmentRelation']);
            $this->syncDerivedColumns($ticket);

            $this->auditLogger->created($ticket, $actor, $startedAt);

            $this->broadcaster->record($ticket, TicketEvent::TYPE_CREATED, [
                'changes' => [
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'department_id' => $ticket->department_id,
                    'category_ids' => $ticket->categories->pluck('id')->all(),
                    'tag_ids' => $ticket->tags->pluck('id')->all(),
                ],
            ], $actor);

            return $ticket;
        });

        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info('ticket.service.create', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'initiator_id' => $actor->getKey(),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'subject_digest' => hash('sha256', (string) $ticket->subject),
            'context' => 'ticket_service',
        ]);

        return $ticket->fresh(['assignee', 'categories', 'tags', 'departmentRelation']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $startedAt = microtime(true);

        $correlationId = $this->resolveCorrelationId();

        [$payload, $categoryIds, $tagIds] = $this->splitAssociations($data);
        $this->hydrateDepartmentName($payload);

        [$ticket, $changeKeys] = DB::transaction(function () use ($ticket, $payload, $categoryIds, $tagIds, $actor, $startedAt) {
            $ticket->load(['categories', 'tags', 'departmentRelation']);

            $ticket->fill($payload);
            $dirty = Arr::except($ticket->getDirty(), ['updated_at']);
            $original = Arr::only($ticket->getOriginal(), array_keys($dirty));
            $ticket->save();

            $categoryChanges = $this->syncAssociations($ticket, 'categories', $categoryIds);
            $tagChanges = $this->syncAssociations($ticket, 'tags', $tagIds);

            if ($categoryChanges) {
                $dirty['categories'] = $categoryChanges['new'];
                $original['categories'] = $categoryChanges['old'];
            }

            if ($tagChanges) {
                $dirty['tags'] = $tagChanges['new'];
                $original['tags'] = $tagChanges['old'];
            }

            $ticket->load(['assignee', 'categories', 'tags', 'departmentRelation']);
            $this->syncDerivedColumns($ticket);

            if (! empty($dirty)) {
                $this->auditLogger->updated($ticket, $actor, $dirty, $original, $startedAt);

                $this->broadcaster->record($ticket, TicketEvent::TYPE_UPDATED, [
                    'changes' => $dirty,
                ], $actor);

                if (array_key_exists('assignee_id', $dirty)) {
                    $this->broadcaster->record($ticket, TicketEvent::TYPE_ASSIGNED, [
                        'assignee_id' => $ticket->assignee_id,
                    ], $actor);
                }
            }

            return [$ticket, array_keys($dirty)];
        });

        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info('ticket.service.update', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'initiator_id' => $actor->getKey(),
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'context' => 'ticket_service',
            'changes_keys' => $changeKeys,
        ]);

        return $ticket->fresh(['assignee', 'categories', 'tags', 'departmentRelation']);
    }

    public function assign(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        $startedAt = microtime(true);
        $original = ['assignee_id' => $ticket->assignee_id];

        $ticket->assignee()->associate($assignee);
        $ticket->save();

        $ticket = $ticket->fresh(['assignee']);

        $this->auditLogger->updated($ticket, $actor, ['assignee_id' => $ticket->assignee_id], $original, $startedAt);

        $this->broadcaster->record($ticket, TicketEvent::TYPE_ASSIGNED, [
            'assignee_id' => $ticket->assignee_id,
        ], $actor);

        return $ticket;
    }

    public function delete(Ticket $ticket, User $actor): void
    {
        $startedAt = microtime(true);

        $ticket->delete();

        $this->auditLogger->deleted($ticket, $actor, $startedAt);
    }

    public function merge(Ticket $primary, Ticket $secondary, User $actor): Ticket
    {
        $this->broadcaster->record($primary->fresh(), TicketEvent::TYPE_MERGED, [
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
        ], $actor);

        Log::channel(config('logging.default'))->info('ticket.lifecycle.merged', [
            'primary_ticket_id' => $primary->getKey(),
            'secondary_ticket_id' => $secondary->getKey(),
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'initiator_id' => $actor->getKey(),
            'context' => 'ticket_lifecycle',
        ]);

        return $primary->fresh();
    }

    protected function resolveCorrelationId(): string
    {
        $header = request()?->header('X-Correlation-ID');

        return $header ? Str::limit($header, 64, '') : (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: array<int>|null, 2: array<int>|null}
     */
    protected function splitAssociations(array $payload): array
    {
        $categories = array_key_exists('category_ids', $payload)
            ? $this->sanitizeIdentifiers($payload['category_ids'])
            : null;
        $tags = array_key_exists('tag_ids', $payload)
            ? $this->sanitizeIdentifiers($payload['tag_ids'])
            : null;

        unset($payload['category_ids'], $payload['tag_ids']);

        return [$payload, $categories, $tags];
    }

    /**
     * @param  array<int|string>|null  $identifiers
     * @return array<int>
     */
    protected function sanitizeIdentifiers(?array $identifiers): array
    {
        if ($identifiers === null) {
            return [];
        }

        $filtered = array_filter(array_map(static fn ($id) => (int) $id, $identifiers), static fn ($id) => $id > 0);

        return array_values(array_unique($filtered));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateDepartmentName(array &$payload): void
    {
        if (! array_key_exists('department_id', $payload)) {
            return;
        }

        if (empty($payload['department_id'])) {
            $payload['department'] = null;

            return;
        }

        $department = TicketDepartment::query()->find($payload['department_id']);
        $payload['department'] = $department?->name;
    }

    protected function syncDerivedColumns(Ticket $ticket): void
    {
        $category = $ticket->categories->first();
        $categoryName = is_object($category) ? $category->name : null;
        $department = $ticket->departmentRelation;
        $departmentName = is_object($department) ? $department->name : null;

        $ticket->forceFill([
            'category' => $categoryName,
            'department' => $departmentName,
        ]);

        if ($ticket->isDirty(['category', 'department'])) {
            $ticket->save();
        }
    }

    /**
     * @return array{old: array<int>, new: array<int>}|null
     */
    protected function syncAssociations(Ticket $ticket, string $relation, ?array $incoming): ?array
    {
        if ($incoming === null) {
            return null;
        }

        $relationModel = $relation === 'categories' ? TicketCategory::class : TicketTag::class;
        $currentIds = $ticket->{$relation}()->pluck((new $relationModel())->getTable().'.id')->all();

        $sortedIncoming = $incoming;
        sort($sortedIncoming);
        $sortedCurrent = $currentIds;
        sort($sortedCurrent);

        if ($sortedIncoming === $sortedCurrent) {
            return null;
        }

        $ticket->{$relation}()->sync($incoming);
        $ticket->load($relation);

        return [
            'old' => $currentIds,
            'new' => $ticket->{$relation}->pluck('id')->all(),
        ];
    }
}
