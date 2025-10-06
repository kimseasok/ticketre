<?php

namespace App\Policies;

use App\Models\TicketDeletionRequest;
use App\Models\User;

class TicketDeletionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.redact');
    }

    public function view(User $user, TicketDeletionRequest $request): bool
    {
        return $user->can('tickets.redact');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.redact');
    }

    public function approve(User $user, TicketDeletionRequest $request): bool
    {
        return $user->can('tickets.redact');
    }

    public function cancel(User $user, TicketDeletionRequest $request): bool
    {
        return $user->can('tickets.redact');
    }
}
