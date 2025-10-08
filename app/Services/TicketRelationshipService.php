<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketRelationshipService
{
    public function __construct(
        private readonly TicketRelationshipAuditLogger $auditLogger,
        private readonly DatabaseManager $database
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): TicketRelationship
    {
        $startedAt = microtime(true);
        $correlation = $this->sanitizeCorrelationId($correlationId ?? $data['correlation_id'] ?? null);

        $tenantId = $this->resolveTenantId($actor);
        $primary = $this->resolveTicket((int) $data['primary_ticket_id'], $tenantId);
        $related = $this->resolveTicket((int) $data['related_ticket_id'], $tenantId);

        $this->guardBrandScope($primary, $related);
        $this->assertRelationshipAvailability($tenantId, $primary->id, $related->id, (string) $data['relationship_type']);

        $context = $this->sanitizeContext(Arr::get($data, 'context', []));

        /** @var TicketRelationship $relationship */
        $relationship = $this->database->transaction(function () use ($data, $primary, $related, $actor, $context, $correlation) {
            return TicketRelationship::create([
                'tenant_id' => $actor->tenant_id,
                'brand_id' => $primary->brand_id,
                'primary_ticket_id' => $primary->getKey(),
                'related_ticket_id' => $related->getKey(),
                'relationship_type' => (string) $data['relationship_type'],
                'created_by' => $actor->getKey(),
                'context' => $context,
                'correlation_id' => $correlation,
            ]);
        });

        $relationship->load(['primaryTicket', 'relatedTicket', 'creator']);

        $this->auditLogger->created($relationship, $actor, $startedAt, $correlation);

        Log::channel(config('logging.default'))->info('ticket.relationship.persisted', [
            'ticket_relationship_id' => $relationship->getKey(),
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'relationship_type' => $relationship->relationship_type,
            'context_keys' => array_keys($context),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlation,
            'context' => 'ticket_relationship',
        ]);

        return $relationship;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TicketRelationship $relationship, array $data, User $actor, ?string $correlationId = null): TicketRelationship
    {
        $startedAt = microtime(true);
        $tenantId = $this->resolveTenantId($actor);

        if ($relationship->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Cannot modify relationships outside the active tenant.',
            ]);
        }

        $type = $data['relationship_type'] ?? $relationship->relationship_type;

        if (! in_array($type, TicketRelationship::TYPES, true)) {
            throw ValidationException::withMessages([
                'relationship_type' => 'Invalid relationship type specified.',
            ]);
        }

        if ($type !== $relationship->relationship_type) {
            $this->assertRelationshipAvailability(
                $tenantId,
                $relationship->primary_ticket_id,
                $relationship->related_ticket_id,
                $type,
                $relationship->getKey()
            );
        }

        $context = $this->sanitizeContext(Arr::get($data, 'context', $relationship->context ?? []));
        $correlation = $this->sanitizeCorrelationId($correlationId ?? $data['correlation_id'] ?? $relationship->correlation_id);

        $original = [
            'relationship_type' => $relationship->relationship_type,
            'context' => $relationship->context,
            'correlation_id' => $relationship->correlation_id,
        ];

        $relationship->fill([
            'relationship_type' => $type,
            'context' => $context,
            'correlation_id' => $correlation,
        ]);
        $relationship->save();

        $changes = [];

        if ($original['relationship_type'] !== $relationship->relationship_type) {
            $changes['relationship_type'] = [
                'old' => $original['relationship_type'],
                'new' => $relationship->relationship_type,
            ];
        }

        if ((array) ($original['context'] ?? []) !== $context) {
            $changes['context_keys'] = [
                'old' => array_keys((array) $original['context']),
                'new' => array_keys($context),
            ];
        }

        if ($original['correlation_id'] !== $relationship->correlation_id) {
            $changes['correlation_id'] = [
                'old' => $original['correlation_id'],
                'new' => $relationship->correlation_id,
            ];
        }

        $relationship->load(['primaryTicket', 'relatedTicket', 'creator']);

        $this->auditLogger->updated($relationship, $actor, $changes, $startedAt, $correlation);

        Log::channel(config('logging.default'))->info('ticket.relationship.updated', [
            'ticket_relationship_id' => $relationship->getKey(),
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'relationship_type' => $relationship->relationship_type,
            'context_keys' => array_keys($context),
            'changed_fields' => array_keys($changes),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlation,
            'context' => 'ticket_relationship',
        ]);

        return $relationship;
    }

    public function delete(TicketRelationship $relationship, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $tenantId = $this->resolveTenantId($actor);

        if ($relationship->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Cannot delete relationships outside the active tenant.',
            ]);
        }

        $correlation = $this->sanitizeCorrelationId($correlationId ?? $relationship->correlation_id ?? (string) Str::uuid());

        $relationship->delete();

        $this->auditLogger->deleted($relationship, $actor, $startedAt, $correlation);

        Log::channel(config('logging.default'))->info('ticket.relationship.deleted', [
            'ticket_relationship_id' => $relationship->getKey(),
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'relationship_type' => $relationship->relationship_type,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlation,
            'context' => 'ticket_relationship',
        ]);
    }

    protected function resolveTenantId(User $actor): int
    {
        /** @var Tenant|null $tenant */
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant) {
            return $tenant->getKey();
        }

        return (int) $actor->tenant_id;
    }

    protected function resolveTicket(int $ticketId, int $tenantId): Ticket
    {
        /** @var Ticket|null $ticket */
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if (! $ticket) {
            throw ValidationException::withMessages([
                'ticket_id' => 'The specified ticket is unavailable for relationship mapping.',
            ]);
        }

        return $ticket;
    }

    protected function guardBrandScope(Ticket $primary, Ticket $related): void
    {
        if ($primary->brand_id !== $related->brand_id) {
            throw ValidationException::withMessages([
                'brand_id' => 'Relationships require tickets from the same brand context.',
            ]);
        }

        /** @var Brand|null $brand */
        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;

        if ($brand && $primary->brand_id !== null && $primary->brand_id !== $brand->getKey()) {
            throw ValidationException::withMessages([
                'brand_id' => 'Tickets must belong to the active brand scope.',
            ]);
        }

        if ($brand && $related->brand_id !== null && $related->brand_id !== $brand->getKey()) {
            throw ValidationException::withMessages([
                'brand_id' => 'Tickets must belong to the active brand scope.',
            ]);
        }
    }

    protected function assertRelationshipAvailability(int $tenantId, int $primaryId, int $relatedId, string $type, ?int $ignoreId = null): void
    {
        if (! in_array($type, TicketRelationship::TYPES, true)) {
            throw ValidationException::withMessages([
                'relationship_type' => 'Unsupported relationship type.',
            ]);
        }

        if ($primaryId === $relatedId) {
            throw ValidationException::withMessages([
                'related_ticket_id' => 'A ticket cannot relate to itself.',
            ]);
        }

        $existsQuery = TicketRelationship::query()
            ->where('tenant_id', $tenantId)
            ->where('primary_ticket_id', $primaryId)
            ->where('related_ticket_id', $relatedId)
            ->where('relationship_type', $type);

        if ($ignoreId) {
            $existsQuery->whereKeyNot($ignoreId);
        }

        if ($existsQuery->exists()) {
            throw ValidationException::withMessages([
                'related_ticket_id' => 'This relationship already exists.',
            ]);
        }

        $inverseQuery = TicketRelationship::query()
            ->where('tenant_id', $tenantId)
            ->where('primary_ticket_id', $relatedId)
            ->where('related_ticket_id', $primaryId)
            ->where('relationship_type', $type);

        if ($ignoreId) {
            $inverseQuery->whereKeyNot($ignoreId);
        }

        if ($inverseQuery->exists()) {
            throw ValidationException::withMessages([
                'related_ticket_id' => 'Inverse relationship already exists for this pair.',
            ]);
        }

        if ($type === TicketRelationship::TYPE_MERGE && $this->wouldCreateMergeCycle($tenantId, $primaryId, $relatedId, $ignoreId)) {
            throw ValidationException::withMessages([
                'relationship_type' => 'This merge would create a circular dependency.',
            ]);
        }
    }

    protected function wouldCreateMergeCycle(int $tenantId, int $primaryId, int $relatedId, ?int $ignoreId = null): bool
    {
        $edges = TicketRelationship::query()
            ->select(['primary_ticket_id', 'related_ticket_id'])
            ->where('tenant_id', $tenantId)
            ->where('relationship_type', TicketRelationship::TYPE_MERGE)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        $adjacency = [];

        foreach ($edges as $edge) {
            $adjacency[$edge->related_ticket_id][] = $edge->primary_ticket_id;
        }

        $visited = [];
        $queue = [$primaryId];

        while ($queue) {
            $node = array_shift($queue);

            if ($node === $relatedId) {
                return true;
            }

            if (isset($visited[$node])) {
                continue;
            }

            $visited[$node] = true;

            foreach ($adjacency[$node] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $queue[] = $neighbor;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, string>
     */
    protected function sanitizeContext($value): array
    {
        $context = [];

        foreach ((array) $value as $key => $raw) {
            $normalizedKey = Str::of((string) $key)->trim()->limit(50, '')->__toString();

            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($raw) || is_object($raw)) {
                $raw = json_encode($raw, JSON_THROW_ON_ERROR);
            }

            $context[$normalizedKey] = Str::of((string) $raw)->trim()->limit(255, '')->__toString();

            if (count($context) >= 20) {
                break;
            }
        }

        return $context;
    }

    protected function sanitizeCorrelationId(?string $value): string
    {
        $correlation = $value ?: (string) Str::uuid();

        return Str::limit($correlation, 64, '');
    }
}
