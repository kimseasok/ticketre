<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketRelationshipService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Ticket $primary, array $payload, User $actor): TicketRelationship
    {
        $startedAt = microtime(true);

        $related = $this->resolveRelatedTicket($primary, (int) $payload['related_ticket_id']);

        $this->assertNoCircularMerge($primary, $related, $payload['relationship_type']);

        $relationship = TicketRelationship::create([
            'tenant_id' => $primary->tenant_id,
            'brand_id' => $primary->brand_id,
            'primary_ticket_id' => $primary->getKey(),
            'related_ticket_id' => $related->getKey(),
            'relationship_type' => $payload['relationship_type'],
            'context' => $payload['context'] ?? null,
            'created_by_id' => $actor->getKey(),
            'updated_by_id' => $actor->getKey(),
        ])->fresh(['primaryTicket', 'relatedTicket']);

        $this->recordAudit('ticket.relationship.created', $relationship, $actor, [
            'relationship_type' => $relationship->relationship_type,
            'related_ticket_id' => $relationship->related_ticket_id,
            'context' => $this->redactContext($relationship->context),
        ]);

        $this->logEvent('ticket.relationship.created', $relationship, $actor, $startedAt);

        return $relationship;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TicketRelationship $relationship, array $payload, User $actor): TicketRelationship
    {
        $startedAt = microtime(true);

        if (isset($payload['relationship_type'])) {
            $related = $relationship->relatedTicket()->firstOrFail();
            $this->assertNoCircularMerge($relationship->primaryTicket()->firstOrFail(), $related, $payload['relationship_type'], $relationship->getKey());
        }

        $original = Arr::only($relationship->getOriginal(), ['relationship_type', 'context']);

        if (array_key_exists('relationship_type', $payload)) {
            $relationship->relationship_type = $payload['relationship_type'];
        }

        if (array_key_exists('context', $payload)) {
            $relationship->context = $payload['context'];
        }

        $relationship->updated_by_id = $actor->getKey();

        $dirty = Arr::except($relationship->getDirty(), ['updated_at']);
        $relationship->save();

        if (! empty($dirty)) {
            $changes = $dirty;

            if (array_key_exists('context', $changes)) {
                $changes['context'] = $this->redactContext($relationship->context);
            }

            $this->recordAudit('ticket.relationship.updated', $relationship, $actor, [
                'changes' => $changes,
                'original' => ['context' => $this->redactContext($original['context'] ?? null)] + Arr::except($original, ['context']),
            ]);
        }

        $relationship->load(['primaryTicket', 'relatedTicket']);

        $this->logEvent('ticket.relationship.updated', $relationship, $actor, $startedAt);

        return $relationship;
    }

    public function delete(TicketRelationship $relationship, User $actor): void
    {
        $startedAt = microtime(true);

        $relationship->delete();

        $this->recordAudit('ticket.relationship.deleted', $relationship, $actor, [
            'relationship_type' => $relationship->relationship_type,
            'related_ticket_id' => $relationship->related_ticket_id,
            'context' => $this->redactContext($relationship->context),
        ]);

        $this->logEvent('ticket.relationship.deleted', $relationship, $actor, $startedAt);
    }

    protected function resolveRelatedTicket(Ticket $primary, int $relatedId): Ticket
    {
        $related = Ticket::query()->whereKey($relatedId)->first();

        if (! $related) {
            throw ValidationException::withMessages([
                'related_ticket_id' => ['The selected related ticket is invalid.'],
            ]);
        }

        if ((int) $related->tenant_id !== (int) $primary->tenant_id) {
            throw ValidationException::withMessages([
                'related_ticket_id' => ['Related ticket must belong to the same tenant.'],
            ]);
        }

        if ((int) $related->brand_id !== (int) $primary->brand_id) {
            throw ValidationException::withMessages([
                'related_ticket_id' => ['Related ticket must belong to the same brand context.'],
            ]);
        }

        if ((int) $related->getKey() === (int) $primary->getKey()) {
            throw ValidationException::withMessages([
                'related_ticket_id' => ['A ticket cannot reference itself.'],
            ]);
        }

        return $related;
    }

    protected function assertNoCircularMerge(Ticket $primary, Ticket $related, string $type, ?int $ignoreId = null): void
    {
        if ($type !== TicketRelationship::TYPE_MERGED) {
            return;
        }

        $existing = TicketRelationship::query()
            ->where('primary_ticket_id', $related->getKey())
            ->where('related_ticket_id', $primary->getKey())
            ->where('relationship_type', TicketRelationship::TYPE_MERGED)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'relationship_type' => ['Circular merge relationships are not allowed.'],
            ]);
        }
    }

    protected function recordAudit(string $action, TicketRelationship $relationship, User $actor, array $changes): void
    {
        AuditLog::create([
            'tenant_id' => $relationship->tenant_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => TicketRelationship::class,
            'auditable_id' => $relationship->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function logEvent(string $action, TicketRelationship $relationship, User $actor, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'relationship_id' => $relationship->getKey(),
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'relationship_type' => $relationship->relationship_type,
            'context_digest' => $this->hashContext($relationship->context),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'context' => 'ticket_relationship',
        ]);
    }

    protected function hashContext(?array $context): ?string
    {
        if ($context === null) {
            return null;
        }

        return hash('sha256', json_encode($context));
    }

    protected function redactContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        return collect($context)
            ->map(fn ($value, $key) => Str::startsWith($key, 'id') ? $value : '[REDACTED]')
            ->all();
    }
}
