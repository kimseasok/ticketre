<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Arr;

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

        $company = Company::create($data);
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

        $company->fill($data);
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
}
