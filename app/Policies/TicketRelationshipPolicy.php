<?php

namespace App\Policies;

use App\Models\TicketRelationship;
use App\Models\User;

class TicketRelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, TicketRelationship $relationship): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.manage');
    }

    public function update(User $user, TicketRelationship $relationship): bool
    {
        return $user->can('tickets.manage');
    }

    public function delete(User $user, TicketRelationship $relationship): bool
    {
        return $user->can('tickets.manage');
    }
}
