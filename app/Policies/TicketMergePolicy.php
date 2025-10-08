<?php

namespace App\Policies;

use App\Models\TicketMerge;
use App\Models\User;

class TicketMergePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.merge');
    }

    public function view(User $user, TicketMerge $merge): bool
    {
        return $user->can('tickets.merge') && $merge->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.merge');
    }
}
