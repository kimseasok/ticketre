<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') && $this->sameTenant($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $this->sameTenant($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $this->sameTenant($user, $role);
    }

    protected function sameTenant(User $user, Role $role): bool
    {
        return (int) $user->tenant_id === (int) $role->tenant_id;
    }
}
