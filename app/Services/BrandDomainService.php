<?php

namespace App\Services;

use App\Jobs\VerifyBrandDomainJob;
use App\Models\Brand;
use App\Models\BrandDomain;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BrandDomainService
{
    public function __construct(private readonly BrandDomainAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Brand $brand, User $actor, ?string $correlationId = null): BrandDomain
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $brand);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var BrandDomain $domain */
        $domain = DB::transaction(fn () => $brand->domains()->create($attributes));
        $domain->refresh();

        $this->auditLogger->created($domain, $actor, $startedAt, $correlation);
        $this->logPerformance('brand_domain.create', $domain, $actor, $startedAt, $correlation);

        return $domain;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BrandDomain $domain, array $data, User $actor, ?string $correlationId = null): BrandDomain
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $domain->brand, $domain);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($domain->getOriginal(), [
            'domain',
            'status',
            'verification_token',
            'ssl_status',
            'verification_error',
            'ssl_error',
        ]);

        $dirty = [];

        DB::transaction(function () use ($domain, $attributes, &$dirty): void {
            $domain->fill($attributes);
            $dirty = Arr::except($domain->getDirty(), ['updated_at']);
            $domain->save();
        });

        $domain->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            if ($field === 'domain') {
                $changes['domain_digest'] = [
                    'old' => isset($original['domain']) ? hash('sha256', (string) $original['domain']) : null,
                    'new' => $domain->domainDigest(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $domain->{$field},
            ];
        }

        $this->auditLogger->updated($domain, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('brand_domain.update', $domain, $actor, $startedAt, $correlation);

        return $domain;
    }

    public function delete(BrandDomain $domain, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $domain->delete());

        $this->auditLogger->deleted($domain, $actor, $startedAt, $correlation);
        $this->logPerformance('brand_domain.delete', $domain, $actor, $startedAt, $correlation);
    }

    public function beginVerification(BrandDomain $domain, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(function () use ($domain, $correlation): void {
            $domain->forceFill([
                'status' => 'verifying',
                'verification_error' => null,
                'ssl_error' => null,
                'correlation_id' => $correlation,
            ])->save();
        });

        $domain->refresh();

        $this->auditLogger->verificationStarted($domain, $actor, $startedAt, $correlation);
        $this->logPerformance('brand_domain.verify.start', $domain, $actor, $startedAt, $correlation);

        VerifyBrandDomainJob::dispatch($domain->getKey(), $actor->getKey(), $correlation)
            ->onQueue('default');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?Brand $brand = null, ?BrandDomain $existing = null): array
    {
        $attributes = Arr::only($data, ['domain']);

        $attributes['tenant_id'] = $brand?->tenant_id
            ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);

        if ($brand) {
            $attributes['brand_id'] = $brand->getKey();
        } elseif (! array_key_exists('brand_id', $attributes) && isset($data['brand_id'])) {
            $attributes['brand_id'] = $data['brand_id'];
        }

        if (isset($attributes['domain'])) {
            $attributes['domain'] = strtolower(trim((string) $attributes['domain']));
        }

        if (! isset($data['verification_token']) || $data['verification_token'] === null) {
            $attributes['verification_token'] = Str::random(40);
        } else {
            $attributes['verification_token'] = Str::limit((string) $data['verification_token'], 64, '');
        }

        if (array_key_exists('status', $data)) {
            $attributes['status'] = $data['status'];
        } elseif ($existing) {
            $attributes['status'] = $existing->status;
        } else {
            $attributes['status'] = 'pending';
        }

        if (array_key_exists('verification_error', $data)) {
            $attributes['verification_error'] = $data['verification_error'];
        } elseif ($existing) {
            $attributes['verification_error'] = $existing->verification_error;
        } else {
            $attributes['verification_error'] = null;
        }

        if (array_key_exists('ssl_error', $data)) {
            $attributes['ssl_error'] = $data['ssl_error'];
        } elseif ($existing) {
            $attributes['ssl_error'] = $existing->ssl_error;
        } else {
            $attributes['ssl_error'] = null;
        }

        return $attributes;
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        if ($correlationId && Str::length($correlationId) <= 64) {
            return $correlationId;
        }

        return (string) Str::uuid();
    }

    protected function logPerformance(string $action, BrandDomain $domain, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'brand_domain_id' => $domain->getKey(),
            'tenant_id' => $domain->tenant_id,
            'brand_id' => $domain->brand_id,
            'domain_digest' => $domain->domainDigest(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_domain',
        ]);
    }
}
