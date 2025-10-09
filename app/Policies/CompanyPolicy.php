<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.manage');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can('companies.manage') && $this->withinScope($user, $company);
    }

    public function create(User $user): bool
    {
        return $user->can('companies.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('companies.manage') && $this->withinScope($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('companies.manage') && $this->withinScope($user, $company);
    }

    protected function withinScope(User $user, Company $company): bool
    {
        if ($company->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($company->brand_id && $user->brand_id && $company->brand_id !== $user->brand_id) {
            return false;
        }

        return true;
    }
}
