<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can('permissions.view')
            && $this->sameTenant($user, $permission)
            && $this->brandAccessible($user, $permission);
    }

    public function create(User $user): bool
    {
        return $user->can('permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage')
            && $this->sameTenant($user, $permission)
            && $this->brandAccessible($user, $permission);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage')
            && $this->sameTenant($user, $permission)
            && $this->brandAccessible($user, $permission);
    }

    protected function sameTenant(User $user, Permission $permission): bool
    {
        if ($permission->tenant_id === null) {
            return false;
        }

        return (int) $user->tenant_id === (int) $permission->tenant_id;
    }

    protected function brandAccessible(User $user, Permission $permission): bool
    {
        if ($permission->brand_id === null) {
            return true;
        }

        return (int) $user->brand_id === (int) $permission->brand_id;
    }
}
