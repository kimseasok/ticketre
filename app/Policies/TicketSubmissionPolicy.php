<?php

namespace App\Policies;

use App\Models\TicketSubmission;
use App\Models\User;

class TicketSubmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TicketSubmission $ticketSubmission): bool
    {
        return $user->can('tickets.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tickets.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TicketSubmission $ticketSubmission): bool
    {
        return $user->can('tickets.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TicketSubmission $ticketSubmission): bool
    {
        return $user->can('tickets.manage');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TicketSubmission $ticketSubmission): bool
    {
        return $user->can('tickets.manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TicketSubmission $ticketSubmission): bool
    {
        return false;
    }
}
