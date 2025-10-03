<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.manage');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }
}
