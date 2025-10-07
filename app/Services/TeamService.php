<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamService
{
    public function __construct(private readonly TeamAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Team
    {
        $startedAt = microtime(true);
        $payload = $this->preparePayload($data);
        $members = $payload['members'] ?? [];

        $team = DB::transaction(function () use ($payload, $members) {
            /** @var Team $team */
            $team = Team::create($payload['attributes']);

            if (! empty($members)) {
                $this->syncMembers($team, $members);
            }

            return $team->fresh(['memberships.user']);
        });

        $this->auditLogger->created($team, $actor, $startedAt);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data, User $actor): Team
    {
        $startedAt = microtime(true);
        $team->loadMissing('memberships');

        $payload = $this->preparePayload($data, $team);
        $members = $payload['members'];

        $original = Arr::only($team->getOriginal(), ['name', 'slug', 'description', 'default_queue', 'brand_id']);

        $result = DB::transaction(function () use ($team, $payload, $members) {
            $team->fill($payload['attributes']);
            $dirtyAttributes = Arr::except($team->getDirty(), ['updated_at']);

            if (! empty($dirtyAttributes)) {
                $team->save();
            }

            $membershipChanges = [];
            if ($members !== null) {
                $membershipChanges = $this->syncMembers($team, $members);
            }

            $team->load(['memberships.user']);

            return [$team, $dirtyAttributes, $membershipChanges];
        });

        /** @var array{0: Team, 1: array<string, mixed>, 2: array<string, mixed>} $result */
        [$team, $dirtyAttributes, $membershipChanges] = $result;

        $changes = [];

        if (! empty($dirtyAttributes)) {
            $changes['attributes'] = $this->formatAttributeChanges($team, $original, $dirtyAttributes);
        }

        if (! empty($membershipChanges)) {
            $changes['memberships'] = $membershipChanges;
        }

        $this->auditLogger->updated($team, $actor, $changes, $startedAt);

        return $team;
    }

    public function delete(Team $team, User $actor): void
    {
        $startedAt = microtime(true);
        $team->loadMissing('memberships');
        $membershipSnapshot = $team->memberships
            ->map(fn (TeamMembership $membership) => [
                'user_id' => $membership->user_id,
                'role' => $membership->role,
                'is_primary' => (bool) $membership->is_primary,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($team) {
            $team->memberships()->delete();
            $team->delete();
        });

        $this->auditLogger->deleted($team, $actor, $membershipSnapshot, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, members: array<int, array<string, mixed>>|null}
     */
    protected function preparePayload(array $data, ?Team $team = null): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'description', 'default_queue', 'brand_id']);

        if ($team) {
            $attributes['tenant_id'] = $team->tenant_id;
        } else {
            $tenantId = $data['tenant_id']
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null)
                ?? $team?->tenant_id;

            if ($tenantId !== null) {
                $attributes['tenant_id'] = $tenantId;
            }
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $brandId = $team?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
            $attributes['brand_id'] = $brandId;
        } else {
            $attributes['brand_id'] = $attributes['brand_id'] ?: null;
        }

        if (array_key_exists('default_queue', $attributes) && $attributes['default_queue'] !== null) {
            $attributes['default_queue'] = (string) $attributes['default_queue'];
        }

        if (array_key_exists('description', $attributes) && $attributes['description'] !== null) {
            $attributes['description'] = (string) $attributes['description'];
        }

        $name = $attributes['name'] ?? $team?->name;

        if (! array_key_exists('slug', $attributes) || empty($attributes['slug'])) {
            $attributes['slug'] = Str::slug(($name ?? 'team').' '.Str::uuid()->toString());
        }

        $members = null;
        if (array_key_exists('members', $data)) {
            $members = array_map(function (array $member): array {
                return [
                    'user_id' => (int) $member['user_id'],
                    'role' => (string) $member['role'],
                    'is_primary' => array_key_exists('is_primary', $member) ? (bool) $member['is_primary'] : false,
                ];
            }, array_values($data['members'] ?? []));
        }

        return [
            'attributes' => $attributes,
            'members' => $members,
        ];
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $dirty
     * @return array<string, array<string, mixed>>
     */
    protected function formatAttributeChanges(Team $team, array $original, array $dirty): array
    {
        $changes = [];

        foreach ($dirty as $field => $_value) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $team->{$field},
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    protected function syncMembers(Team $team, array $members): array
    {
        $changes = [
            'attached' => [],
            'updated' => [],
            'detached' => [],
        ];

        $desired = collect($members)->keyBy('user_id');
        $active = $team->memberships()->get()->keyBy('user_id');

        foreach ($desired as $userId => $memberData) {
            /** @var TeamMembership|null $membership */
            $membership = $active->get($userId);

            if (! $membership) {
                $restored = $team->memberships()->onlyTrashed()->where('user_id', $userId)->first();

                if ($restored) {
                    $restored->fill([
                        'role' => $memberData['role'],
                        'is_primary' => $memberData['is_primary'],
                        'tenant_id' => $team->tenant_id,
                    ]);
                    if ($restored->isDirty()) {
                        $restored->save();
                    }
                    $restored->restore();
                    $membership = $restored->fresh();
                    $active->put($userId, $membership);

                    $changes['attached'][] = [
                        'user_id' => $userId,
                        'role' => $membership->role,
                        'is_primary' => (bool) $membership->is_primary,
                        'restored' => true,
                    ];
                } else {
                    $membership = $team->memberships()->create([
                        'tenant_id' => $team->tenant_id,
                        'user_id' => $userId,
                        'role' => $memberData['role'],
                        'is_primary' => $memberData['is_primary'],
                    ]);

                    $active->put($userId, $membership);

                    $changes['attached'][] = [
                        'user_id' => $userId,
                        'role' => $membership->role,
                        'is_primary' => (bool) $membership->is_primary,
                        'restored' => false,
                    ];
                }

                continue;
            }

            $memberChanges = [];

            if ($membership->role !== $memberData['role']) {
                $memberChanges['role'] = [
                    'old' => $membership->role,
                    'new' => $memberData['role'],
                ];
                $membership->role = $memberData['role'];
            }

            if ((bool) $membership->is_primary !== $memberData['is_primary']) {
                $memberChanges['is_primary'] = [
                    'old' => (bool) $membership->is_primary,
                    'new' => $memberData['is_primary'],
                ];
                $membership->is_primary = $memberData['is_primary'];
            }

            if (! empty($memberChanges)) {
                $membership->tenant_id = $team->tenant_id;
                $membership->save();

                $changes['updated'][] = [
                    'user_id' => $userId,
                    'changes' => $memberChanges,
                ];
            }
        }

        foreach ($active as $userId => $membership) {
            if ($desired->has($userId)) {
                continue;
            }

            $membership->delete();

            $changes['detached'][] = [
                'user_id' => $userId,
                'role' => $membership->role,
                'is_primary' => (bool) $membership->is_primary,
            ];
        }

        return array_filter($changes, fn ($value) => ! empty($value));
    }
}
