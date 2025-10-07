<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TeamAuditLogger
{
    public function created(Team $team, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => $this->teamSnapshot($team),
        ];

        $this->persist($team, $actor, 'team.created', $payload);
        $this->logEvent('team.created', $team, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Team $team, ?User $actor, array $changes, float $startedAt): void
    {
        if (empty($changes)) {
            return;
        }

        $payload = $this->sanitizeChanges($changes);

        $this->persist($team, $actor, 'team.updated', $payload);
        $this->logEvent('team.updated', $team, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $memberships
     */
    public function deleted(Team $team, ?User $actor, array $memberships, float $startedAt): void
    {
        $payload = [
            'snapshot' => $this->teamSnapshot($team, $memberships),
        ];

        $this->persist($team, $actor, 'team.deleted', $payload);
        $this->logEvent('team.deleted', $team, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Team $team, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Team::class,
            'auditable_id' => $team->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Team $team, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $correlationId = request()?->header('X-Correlation-ID');

        $memberCount = null;

        if (isset($payload['snapshot']['members']) && is_array($payload['snapshot']['members'])) {
            $memberCount = count($payload['snapshot']['members']);
        } elseif ($team->relationLoaded('memberships')) {
            $memberCount = $team->memberships->count();
        } else {
            $memberCount = $team->memberships()->count();
        }

        Log::channel(config('logging.default'))->info($action, [
            'team_id' => $team->getKey(),
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'member_count' => $memberCount,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'team_audit',
            'correlation_id' => $correlationId,
            'payload_keys' => array_keys($payload),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $memberships
     * @return array<string, mixed>
     */
    protected function teamSnapshot(Team $team, ?array $memberships = null): array
    {
        return [
            'name' => $team->name,
            'slug' => $team->slug,
            'brand_id' => $team->brand_id,
            'default_queue' => $team->default_queue,
            'description_digest' => $this->hashNullable($team->description),
            'members' => $memberships ?? $this->membershipSnapshot($team),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function membershipSnapshot(Team $team): array
    {
        return $team->memberships
            ->map(fn ($membership) => [
                'user_id' => $membership->user_id,
                'role' => $membership->role,
                'is_primary' => (bool) $membership->is_primary,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function sanitizeChanges(array $changes): array
    {
        if (isset($changes['attributes']) && isset($changes['attributes']['description'])) {
            $changes['attributes']['description_digest'] = [
                'old' => $this->hashNullable($changes['attributes']['description']['old'] ?? null),
                'new' => $this->hashNullable($changes['attributes']['description']['new'] ?? null),
            ];

            unset($changes['attributes']['description']);
        }

        return $changes;
    }

    protected function hashNullable(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
    }
}
