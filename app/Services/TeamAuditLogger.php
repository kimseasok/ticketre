<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TeamAuditLogger
{
    public function created(Team $team, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->teamSnapshot($team),
        ];

        $this->persist($team, $actor, 'team.created', $payload, $correlationId);
        $this->logEvent('team.created', $team, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Team $team, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($team, $actor, 'team.updated', $changes, $correlationId);
        $this->logEvent('team.updated', $team, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(Team $team, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->teamSnapshot($team),
        ];

        $this->persist($team, $actor, 'team.deleted', $payload, $correlationId);
        $this->logEvent('team.deleted', $team, $actor, $startedAt, $payload, $correlationId);
    }

    public function membershipAttached(TeamMembership $membership, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = $this->membershipPayload($membership);

        $this->persistMembership($membership, 'team.membership.attached', $actor, $payload, $correlationId);
        $this->logMembership('team.membership.attached', $membership, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function membershipUpdated(TeamMembership $membership, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persistMembership($membership, 'team.membership.updated', $actor, $changes, $correlationId);
        $this->logMembership('team.membership.updated', $membership, $actor, $startedAt, $changes, $correlationId);
    }

    public function membershipDetached(TeamMembership $membership, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = $this->membershipPayload($membership);

        $this->persistMembership($membership, 'team.membership.detached', $actor, $payload, $correlationId);
        $this->logMembership('team.membership.detached', $membership, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamSnapshot(Team $team): array
    {
        return [
            'name_hash' => $this->hashValue($team->name),
            'brand_id' => $team->brand_id,
            'default_queue' => $team->default_queue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function membershipPayload(TeamMembership $membership): array
    {
        return [
            'team_id' => $membership->team_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'is_primary' => $membership->is_primary,
            'user_email_hash' => $this->hashValue($membership->user?->email),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Team $team, User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => Team::class,
            'auditable_id' => $team->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persistMembership(TeamMembership $membership, string $action, User $actor, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $membership->tenant_id,
            'brand_id' => $membership->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => TeamMembership::class,
            'auditable_id' => $membership->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Team $team, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'team_id' => $team->getKey(),
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'team_audit',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logMembership(string $action, TeamMembership $membership, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'team_id' => $membership->team_id,
            'membership_id' => $membership->getKey(),
            'tenant_id' => $membership->tenant_id,
            'brand_id' => $membership->brand_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'team_membership_audit',
        ]);
    }

    protected function hashValue(?string $value): string
    {
        return hash('sha256', (string) $value);
    }
}
