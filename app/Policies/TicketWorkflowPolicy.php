<?php

namespace App\Policies;

use App\Models\TicketWorkflow;
use App\Models\User;

class TicketWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.workflows.view');
    }

    public function view(User $user, TicketWorkflow $workflow): bool
    {
        return $user->can('tickets.workflows.view') && $workflow->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.workflows.manage');
    }

    public function update(User $user, TicketWorkflow $workflow): bool
    {
        return $user->can('tickets.workflows.manage') && $workflow->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, TicketWorkflow $workflow): bool
    {
        return $user->can('tickets.workflows.manage') && $workflow->tenant_id === $user->tenant_id;
    }
}
