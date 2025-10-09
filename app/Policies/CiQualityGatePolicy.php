<?php

namespace App\Policies;

use App\Models\CiQualityGate;
use App\Models\User;

class CiQualityGatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ci.quality_gates.view');
    }

    public function view(User $user, CiQualityGate $gate): bool
    {
        return $user->can('ci.quality_gates.view')
            && $this->sameTenant($user, $gate)
            && $this->brandAccessible($user, $gate);
    }

    public function create(User $user): bool
    {
        return $user->can('ci.quality_gates.manage');
    }

    public function update(User $user, CiQualityGate $gate): bool
    {
        return $user->can('ci.quality_gates.manage')
            && $this->sameTenant($user, $gate)
            && $this->brandAccessible($user, $gate);
    }

    public function delete(User $user, CiQualityGate $gate): bool
    {
        return $user->can('ci.quality_gates.manage')
            && $this->sameTenant($user, $gate)
            && $this->brandAccessible($user, $gate);
    }

    protected function sameTenant(User $user, CiQualityGate $gate): bool
    {
        return (int) $user->tenant_id === (int) $gate->tenant_id;
    }

    protected function brandAccessible(User $user, CiQualityGate $gate): bool
    {
        if ($gate->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $gate->brand_id;
    }
}
