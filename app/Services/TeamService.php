<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeamService
{
    public function __construct(private readonly TeamAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): Team
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var Team $team */
        $team = DB::transaction(function () use ($attributes) {
            return Team::create($attributes);
        });

        $team->refresh();

        $this->auditLogger->created($team, $actor, $startedAt, $correlation);
        $this->logPerformance('team.create', $team, $actor, $startedAt, $correlation);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data, User $actor, ?string $correlationId = null): Team
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor, $team);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($team->getOriginal(), ['name', 'slug', 'default_queue', 'description', 'brand_id']);

        $dirty = [];

        DB::transaction(function () use ($team, $attributes, &$dirty) {
            $team->fill($attributes);
            $dirty = Arr::except($team->getDirty(), ['updated_at']);
            $team->save();
        });

        $team->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $team->{$field},
            ];
        }

        $this->auditLogger->updated($team, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('team.update', $team, $actor, $startedAt, $correlation);

        return $team;
    }

    public function delete(Team $team, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(function () use ($team) {
            $team->delete();
        });

        $this->auditLogger->deleted($team, $actor, $startedAt, $correlation);
        $this->logPerformance('team.delete', $team, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, User $actor, ?Team $team = null): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'default_queue', 'description', 'brand_id']);

        if (! isset($attributes['name']) && ! $team) {
            $attributes['name'] = 'Team '.Str::uuid()->toString();
        }

        if (array_key_exists('slug', $attributes) && empty($attributes['slug'])) {
            unset($attributes['slug']);
        }

        if (! array_key_exists('slug', $attributes)) {
            $attributes['slug'] = Str::slug(($attributes['name'] ?? $team?->name ?? 'team').'-'.Str::random(6));
        }

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $team?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : $actor->tenant_id);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $team?->brand_id
                ?? ($data['brand_id'] ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : $actor->brand_id));
        }

        return $attributes;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function logPerformance(string $action, Team $team, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'team_id' => $team->getKey(),
            'tenant_id' => $team->tenant_id,
            'brand_id' => $team->brand_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'team_service',
        ]);
    }
}
