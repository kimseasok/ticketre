<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.manage') || $user->can('contacts.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can('contacts.manage') || $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('contacts.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('contacts.manage');
    }
}
