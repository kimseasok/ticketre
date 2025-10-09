<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompanyService
{
    public function __construct(private readonly CompanyAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): Company
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var Company $company */
        $company = DB::transaction(function () use ($attributes) {
            return Company::create($attributes);
        });

        $company->refresh();

        $this->auditLogger->created($company, $actor, $startedAt, $correlation);
        $this->logPerformance('company.create', $company, $actor, $startedAt, $correlation);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data, User $actor, ?string $correlationId = null): Company
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor, $company);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($company->getOriginal(), [
            'name',
            'domain',
            'brand_id',
            'metadata',
            'tags',
        ]);

        $dirty = [];

        DB::transaction(function () use ($company, $attributes, &$dirty) {
            $company->fill($attributes);
            $dirty = Arr::except($company->getDirty(), ['updated_at']);
            $company->save();
        });

        $company->refresh();

        $changes = [];
        foreach ($dirty as $field => $_value) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $company->{$field},
            ];
        }

        $this->auditLogger->updated($company, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('company.update', $company, $actor, $startedAt, $correlation, array_keys($dirty));

        return $company;
    }

    public function delete(Company $company, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(function () use ($company) {
            $company->delete();
        });

        $this->auditLogger->deleted($company, $actor, $startedAt, $correlation);
        $this->logPerformance('company.delete', $company, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, User $actor, ?Company $company = null): array
    {
        $attributes = Arr::only($data, ['name', 'domain', 'brand_id', 'metadata', 'tags']);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $company?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : $actor->tenant_id);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $company?->brand_id
                ?? ($data['brand_id'] ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : $actor->brand_id));
        }

        if (array_key_exists('domain', $attributes) && $attributes['domain']) {
            $attributes['domain'] = Str::lower(trim((string) $attributes['domain']));
        }

        $attributes['tags'] = $this->normaliseTags($attributes['tags'] ?? ($company?->tags ?? []));
        $attributes['metadata'] = $this->normaliseMetadata($attributes['metadata'] ?? ($company?->metadata ?? []));

        return $attributes;
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return array<int, string>
     */
    protected function normaliseTags(array|string|null $value): array
    {
        $tags = is_array($value) ? $value : (is_string($value) ? [$value] : []);

        $normalised = [];
        foreach ($tags as $tag) {
            $trimmed = trim((string) $tag);

            if ($trimmed === '') {
                continue;
            }

            $normalised[] = Str::limit(Str::lower($trimmed), 64, '');
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function normaliseMetadata(array $metadata): array
    {
        $sanitised = [];

        foreach ($metadata as $key => $value) {
            $sanitised[Str::limit((string) $key, 64, '')] = $value;
        }

        return $sanitised;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function logPerformance(string $action, Company $company, User $actor, float $startedAt, string $correlationId, array $fields = []): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'company_id' => $company->getKey(),
            'tenant_id' => $company->tenant_id,
            'brand_id' => $company->brand_id,
            'tags_count' => count($company->tags ?? []),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'changed_fields' => $fields,
            'correlation_id' => $correlationId,
            'context' => 'company_service',
        ]);
    }
}
