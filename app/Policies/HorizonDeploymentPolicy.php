<?php

namespace App\Policies;

use App\Models\HorizonDeployment;
use App\Models\User;

class HorizonDeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('infrastructure.horizon.view');
    }

    public function view(User $user, HorizonDeployment $deployment): bool
    {
        return $user->can('infrastructure.horizon.view')
            && $this->sameTenant($user, $deployment)
            && $this->brandAccessible($user, $deployment);
    }

    public function create(User $user): bool
    {
        return $user->can('infrastructure.horizon.manage');
    }

    public function update(User $user, HorizonDeployment $deployment): bool
    {
        return $user->can('infrastructure.horizon.manage')
            && $this->sameTenant($user, $deployment)
            && $this->brandAccessible($user, $deployment);
    }

    public function delete(User $user, HorizonDeployment $deployment): bool
    {
        return $user->can('infrastructure.horizon.manage')
            && $this->sameTenant($user, $deployment)
            && $this->brandAccessible($user, $deployment);
    }

    protected function sameTenant(User $user, HorizonDeployment $deployment): bool
    {
        return (int) $user->tenant_id === (int) $deployment->tenant_id;
    }

    protected function brandAccessible(User $user, HorizonDeployment $deployment): bool
    {
        if ($deployment->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $deployment->brand_id;
    }
}
