<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketRelationshipAuditLogger
{
    public function created(TicketRelationship $relationship, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($relationship),
        ];

        $this->persist($relationship, $actor, 'ticket.relationship.created', $payload);
        $this->logEvent('ticket.relationship.created', $relationship, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(TicketRelationship $relationship, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($relationship, $actor, 'ticket.relationship.updated', $changes);
        $this->logEvent('ticket.relationship.updated', $relationship, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(TicketRelationship $relationship, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($relationship),
        ];

        $this->persist($relationship, $actor, 'ticket.relationship.deleted', $payload);
        $this->logEvent('ticket.relationship.deleted', $relationship, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(TicketRelationship $relationship): array
    {
        return [
            'relationship_type' => $relationship->relationship_type,
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'context_keys' => array_values(array_keys((array) $relationship->context)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(TicketRelationship $relationship, User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => TicketRelationship::class,
            'auditable_id' => $relationship->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, TicketRelationship $relationship, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $contextKeys = $payload['snapshot']['context_keys'] ?? array_keys((array) $relationship->context);
        $changeFields = array_keys($payload);

        Log::channel(config('logging.default'))->info($action, [
            'ticket_relationship_id' => $relationship->getKey(),
            'tenant_id' => $relationship->tenant_id,
            'brand_id' => $relationship->brand_id,
            'primary_ticket_id' => $relationship->primary_ticket_id,
            'related_ticket_id' => $relationship->related_ticket_id,
            'relationship_type' => $relationship->relationship_type,
            'context_keys' => array_values($contextKeys),
            'change_fields' => $changeFields,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'ticket_relationship',
        ]);
    }
}
