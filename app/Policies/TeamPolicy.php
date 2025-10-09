<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can('teams.view') && $team->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->can('teams.manage') && $team->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->can('teams.manage') && $team->tenant_id === $user->tenant_id;
    }
}
