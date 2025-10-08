<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TicketMerge;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketMergeAuditLogger
{
    public function created(TicketMerge $merge, User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => $this->snapshot($merge),
        ];

        $this->persist($merge, $actor, 'ticket.merge.created', $payload);
        $this->log('ticket.merge.created', $merge, $actor, $startedAt, $payload);
    }

    public function completed(TicketMerge $merge, User $actor, array $summary, float $startedAt): void
    {
        $payload = [
            'summary' => $summary,
            'status' => TicketMerge::STATUS_COMPLETED,
        ];

        $this->persist($merge, $actor, 'ticket.merge.completed', $payload);
        $this->log('ticket.merge.completed', $merge, $actor, $startedAt, $payload);
    }

    public function failed(TicketMerge $merge, User $actor, string $reason, float $startedAt): void
    {
        $payload = [
            'status' => TicketMerge::STATUS_FAILED,
            'reason_digest' => hash('sha256', $reason),
        ];

        $this->persist($merge, $actor, 'ticket.merge.failed', $payload);
        $this->log('ticket.merge.failed', $merge, $actor, $startedAt, $payload);
    }

    protected function snapshot(TicketMerge $merge): array
    {
        return [
            'primary_ticket_id' => $merge->primary_ticket_id,
            'secondary_ticket_id' => $merge->secondary_ticket_id,
            'status' => $merge->status,
            'correlation_id' => $merge->correlation_id,
        ];
    }

    protected function persist(TicketMerge $merge, User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $merge->tenant_id,
            'brand_id' => $merge->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => TicketMerge::class,
            'auditable_id' => $merge->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function log(string $action, TicketMerge $merge, User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'ticket_merge_id' => $merge->getKey(),
            'tenant_id' => $merge->tenant_id,
            'brand_id' => $merge->brand_id,
            'primary_ticket_id' => $merge->primary_ticket_id,
            'secondary_ticket_id' => $merge->secondary_ticket_id,
            'status' => $merge->status,
            'user_id' => $actor->getKey(),
            'correlation_id' => $merge->correlation_id,
            'duration_ms' => round($durationMs, 2),
            'context' => 'ticket_merge',
            'summary_keys' => array_keys($payload['summary'] ?? []),
        ]);
    }
}
