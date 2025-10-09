<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamMembershipService
{
    public function __construct(private readonly TeamAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function attach(Team $team, array $data, User $actor, ?string $correlationId = null): TeamMembership
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($team, $data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var TeamMembership $membership */
        $membership = DB::transaction(function () use ($attributes) {
            return TeamMembership::create($attributes);
        });

        $membership->loadMissing('user');

        $this->auditLogger->membershipAttached($membership, $actor, $startedAt, $correlation);

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TeamMembership $membership, array $data, User $actor, ?string $correlationId = null): TeamMembership
    {
        $startedAt = microtime(true);
        $payload = $this->prepareUpdate($data, $membership);
        $correlation = $this->resolveCorrelationId($correlationId);

        if (empty($payload)) {
            return $membership;
        }

        $original = Arr::only($membership->getOriginal(), ['role', 'is_primary', 'joined_at']);

        DB::transaction(function () use ($membership, $payload) {
            $membership->fill($payload);
            $membership->save();
        });

        $membership->refresh()->loadMissing('user');

        $changes = [];
        foreach ($payload as $field => $_value) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $membership->{$field},
            ];
        }

        $this->auditLogger->membershipUpdated($membership, $actor, $changes, $startedAt, $correlation);

        return $membership;
    }

    public function detach(TeamMembership $membership, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        $membership->loadMissing('user');

        DB::transaction(function () use ($membership) {
            $membership->delete();
        });

        $this->auditLogger->membershipDetached($membership, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(Team $team, array $data): array
    {
        $attributes = [
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'team_id' => $team->getKey(),
            'user_id' => (int) $data['user_id'],
            'role' => $data['role'] ?? TeamMembership::ROLE_MEMBER,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'joined_at' => $data['joined_at'] ?? now(),
        ];

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareUpdate(array $data, TeamMembership $membership): array
    {
        $payload = [];

        if (array_key_exists('role', $data)) {
            $payload['role'] = $data['role'];
        }

        if (array_key_exists('is_primary', $data)) {
            $payload['is_primary'] = (bool) $data['is_primary'];
        }

        if (array_key_exists('joined_at', $data)) {
            $payload['joined_at'] = $data['joined_at'];
        }

        return $payload;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }
}
