<?php

namespace App\Services;

use App\Models\HorizonDeployment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HorizonDeploymentService
{
    public function __construct(
        private readonly HorizonDeploymentAuditLogger $auditLogger,
        private readonly HorizonDeploymentHealthService $healthService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): HorizonDeployment
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var HorizonDeployment $deployment */
        $deployment = DB::transaction(fn () => HorizonDeployment::create($attributes));
        $deployment->refresh();

        $this->auditLogger->created($deployment, $actor, $startedAt, $correlation);
        $this->logPerformance('horizon.deployment.created', $deployment, $actor, $startedAt, $correlation);
        $this->healthService->check($deployment, $correlation);

        return $deployment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(HorizonDeployment $deployment, array $data, User $actor, ?string $correlationId = null): HorizonDeployment
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $deployment);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($deployment->getOriginal(), [
            'name',
            'slug',
            'domain',
            'auth_guard',
            'horizon_connection',
            'uses_tls',
            'supervisors',
            'ssl_certificate_expires_at',
            'metadata',
        ]);

        $dirty = [];

        DB::transaction(function () use ($deployment, $attributes, &$dirty): void {
            $deployment->fill($attributes);
            $dirty = Arr::except($deployment->getDirty(), ['updated_at']);
            $deployment->save();
        });

        $deployment->refresh();

        $changes = [];
        foreach ($dirty as $field => $value) {
            if ($field === 'domain') {
                $changes['domain_digest'] = [
                    'old' => isset($original['domain']) ? hash('sha256', strtolower((string) $original['domain'])) : null,
                    'new' => $deployment->domainDigest(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $value,
            ];
        }

        $this->auditLogger->updated($deployment, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('horizon.deployment.updated', $deployment, $actor, $startedAt, $correlation);
        $this->healthService->check($deployment, $correlation);

        return $deployment;
    }

    public function delete(HorizonDeployment $deployment, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $deployment->delete());

        $this->auditLogger->deleted($deployment, $actor, $startedAt, $correlation);
        $this->logPerformance('horizon.deployment.deleted', $deployment, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?HorizonDeployment $existing = null): array
    {
        $attributes = Arr::only($data, [
            'tenant_id',
            'brand_id',
            'name',
            'slug',
            'domain',
            'auth_guard',
            'horizon_connection',
            'uses_tls',
            'supervisors',
            'last_deployed_at',
            'ssl_certificate_expires_at',
            'metadata',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $existing?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $existing?->brand_id
                ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null);
        }

        $attributes['name'] = trim((string) ($attributes['name'] ?? $existing?->name ?? 'Horizon Deployment'));

        if (empty($attributes['slug'])) {
            $source = $attributes['name'] ?? $existing?->name ?? 'horizon-deployment';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        if (! empty($attributes['domain'])) {
            $attributes['domain'] = strtolower(trim((string) $attributes['domain']));
        } elseif ($existing) {
            $attributes['domain'] = $existing->domain;
        }

        $attributes['auth_guard'] = $attributes['auth_guard'] ?? $existing?->auth_guard ?? 'web';
        $attributes['horizon_connection'] = $attributes['horizon_connection'] ?? $existing?->horizon_connection ?? config('queue.default', 'sync');
        $attributes['uses_tls'] = isset($attributes['uses_tls']) ? (bool) $attributes['uses_tls'] : ($existing?->uses_tls ?? true);

        if (isset($attributes['last_deployed_at'])) {
            $attributes['last_deployed_at'] = $this->parseDate($attributes['last_deployed_at']);
        }

        if (isset($attributes['ssl_certificate_expires_at'])) {
            $attributes['ssl_certificate_expires_at'] = $this->parseDate($attributes['ssl_certificate_expires_at']);
        }

        if (! isset($attributes['metadata'])) {
            $attributes['metadata'] = $existing?->metadata ?? [];
        }

        $attributes['supervisors'] = $this->normalizeSupervisors($attributes['supervisors'] ?? $existing?->supervisors ?? []);

        return $attributes;
    }

    /**
     * @param  array<int, mixed>  $definitions
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSupervisors(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? 'supervisor-'.Str::random(4));
            $queues = array_values(array_filter(array_map(fn ($queue) => Str::of((string) $queue)->trim()->limit(64, '')->toString(), Arr::wrap($definition['queue'] ?? []))));

            if (empty($queues)) {
                $queues = ['default'];
            }

            $normalized[] = [
                'name' => Str::slug($name, '_'),
                'connection' => (string) ($definition['connection'] ?? 'redis'),
                'queue' => $queues,
                'balance' => (string) ($definition['balance'] ?? 'auto'),
                'min_processes' => isset($definition['min_processes']) ? max(0, (int) $definition['min_processes']) : 1,
                'max_processes' => isset($definition['max_processes']) ? max(1, (int) $definition['max_processes']) : 10,
                'max_jobs' => isset($definition['max_jobs']) ? max(0, (int) $definition['max_jobs']) : 0,
                'max_time' => isset($definition['max_time']) ? max(0, (int) $definition['max_time']) : 0,
                'timeout' => isset($definition['timeout']) ? max(1, (int) $definition['timeout']) : 60,
                'tries' => isset($definition['tries']) ? max(1, (int) $definition['tries']) : 1,
            ];
        }

        if (empty($normalized)) {
            $normalized[] = [
                'name' => 'app-supervisor',
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'min_processes' => 1,
                'max_processes' => 5,
                'max_jobs' => 0,
                'max_time' => 0,
                'timeout' => 60,
                'tries' => 1,
            ];
        }

        return $normalized;
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $candidate = trim((string) $value);

        if ($candidate !== '') {
            return Str::of($candidate)->limit(64, '')->toString();
        }

        return Str::uuid()->toString();
    }

    protected function logPerformance(string $action, HorizonDeployment $deployment, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'horizon_deployment_id' => $deployment->getKey(),
            'tenant_id' => $deployment->tenant_id,
            'brand_id' => $deployment->brand_id,
            'domain_digest' => $deployment->domainDigest(),
            'supervisor_count' => $deployment->supervisorCount(),
            'uses_tls' => $deployment->uses_tls,
            'connection' => $deployment->horizon_connection,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'horizon_deployment_service',
        ]);
    }
}
