<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CompanyService
{
    public function __construct(private readonly CompanyAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Company
    {
        $startedAt = microtime(true);

        $attributes = $this->prepareAttributes($data);

        $company = Company::create($attributes);
        $company->refresh();

        $this->auditLogger->created($company, $actor, $startedAt);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data, User $actor): Company
    {
        $startedAt = microtime(true);

        $attributes = $this->prepareAttributes($data, $company);

        $company->fill($attributes);
        $dirty = Arr::except($company->getDirty(), ['updated_at']);
        $original = Arr::only($company->getOriginal(), array_keys($dirty));
        $company->save();

        $company->refresh();

        if (! empty($dirty)) {
            $this->auditLogger->updated($company, $actor, $dirty, $original, $startedAt);
        }

        return $company;
    }

    public function delete(Company $company, User $actor): void
    {
        $startedAt = microtime(true);

        $company->delete();

        $this->auditLogger->deleted($company, $actor, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?Company $company = null): array
    {
        $attributes = $data;

        if (array_key_exists('metadata', $attributes)) {
            $attributes['metadata'] = $this->normalizeMetadata($attributes['metadata']);
        }

        if (array_key_exists('domain', $attributes)) {
            $attributes['domain'] = $attributes['domain']
                ? Str::lower((string) $attributes['domain'])
                : null;
        }

        $allowed = ['name', 'domain', 'metadata'];

        if (! $company) {
            $allowed[] = 'tenant_id';
        }

        return Arr::only($attributes, $allowed);
    }

    /**
     * @param  mixed  $metadata
     * @return array<string, mixed>
     */
    protected function normalizeMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        /** @var array<string, mixed> $filtered */
        $filtered = collect($metadata)
            ->filter(fn ($value): bool => $value !== null)
            ->toArray();

        return $filtered;
    }
}
