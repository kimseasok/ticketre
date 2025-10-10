<?php

namespace App\Policies;

use App\Models\SlaPolicy;
use App\Models\User;

class SlaPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sla.policies.view');
    }

    public function view(User $user, SlaPolicy $policy): bool
    {
        return $user->can('sla.policies.view')
            && $this->sameTenant($user, $policy)
            && $this->brandAccessible($user, $policy);
    }

    public function create(User $user): bool
    {
        return $user->can('sla.policies.manage');
    }

    public function update(User $user, SlaPolicy $policy): bool
    {
        return $user->can('sla.policies.manage')
            && $this->sameTenant($user, $policy)
            && $this->brandAccessible($user, $policy);
    }

    public function delete(User $user, SlaPolicy $policy): bool
    {
        return $user->can('sla.policies.manage')
            && $this->sameTenant($user, $policy)
            && $this->brandAccessible($user, $policy);
    }

    protected function sameTenant(User $user, SlaPolicy $policy): bool
    {
        return (int) $user->tenant_id === (int) $policy->tenant_id;
    }

    protected function brandAccessible(User $user, SlaPolicy $policy): bool
    {
        if ($policy->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $policy->brand_id;
    }
}
