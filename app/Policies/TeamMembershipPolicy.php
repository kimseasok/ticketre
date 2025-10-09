<?php

namespace App\Policies;

use App\Models\TeamMembership;
use App\Models\User;

class TeamMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    public function view(User $user, TeamMembership $membership): bool
    {
        return $user->can('teams.view') && $membership->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('teams.manage');
    }

    public function update(User $user, TeamMembership $membership): bool
    {
        return $user->can('teams.manage') && $membership->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, TeamMembership $membership): bool
    {
        return $user->can('teams.manage') && $membership->tenant_id === $user->tenant_id;
    }
}
