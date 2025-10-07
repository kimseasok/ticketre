<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can('teams.view')
            && $this->sameTenant($user, $team)
            && $this->sameBrandOrManager($user, $team);
    }

    public function create(User $user): bool
    {
        return $user->can('teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->can('teams.manage')
            && $this->sameTenant($user, $team)
            && $this->sameBrandOrManager($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->can('teams.manage')
            && $this->sameTenant($user, $team)
            && $this->sameBrandOrManager($user, $team);
    }

    protected function sameTenant(User $user, Team $team): bool
    {
        return (int) $user->tenant_id === (int) $team->tenant_id;
    }

    protected function sameBrandOrManager(User $user, Team $team): bool
    {
        if ($team->brand_id === null) {
            return true;
        }

        if ((int) $user->brand_id === (int) $team->brand_id) {
            return true;
        }

        return $user->can('teams.manage');
    }
}
