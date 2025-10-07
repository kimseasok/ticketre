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
        return $user->can('permissions.view') && $this->accessible($user, $permission);
    }

    public function create(User $user): bool
    {
        return $user->can('permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage') && $this->accessible($user, $permission);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('permissions.manage') && $this->accessible($user, $permission);
    }

    protected function accessible(User $user, Permission $permission): bool
    {
        if ($permission->tenant_id === null) {
            return true;
        }

        return (int) $permission->tenant_id === (int) $user->tenant_id;
    }
}
