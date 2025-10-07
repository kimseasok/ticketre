<?php

namespace App\Policies;

use App\Models\BroadcastConnection;
use App\Models\User;

class BroadcastConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('broadcast_connections.view');
    }

    public function view(User $user, BroadcastConnection $broadcastConnection): bool
    {
        return $user->can('broadcast_connections.view');
    }

    public function create(User $user): bool
    {
        return $user->can('broadcast_connections.manage');
    }

    public function update(User $user, BroadcastConnection $broadcastConnection): bool
    {
        return $user->can('broadcast_connections.manage');
    }

    public function delete(User $user, BroadcastConnection $broadcastConnection): bool
    {
        return $user->can('broadcast_connections.manage');
    }
}
