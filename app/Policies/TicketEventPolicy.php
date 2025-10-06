<?php

namespace App\Policies;

use App\Models\TicketEvent;
use App\Models\User;

class TicketEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, TicketEvent $event): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.manage');
    }

    public function delete(User $user, TicketEvent $event): bool
    {
        return $user->can('tickets.manage');
    }
}
