<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Ticket $ticket): bool
    {
        if (! $user->can('tickets.view')) {
            return false;
        }

        return $this->belongsToTenantAndBrand($user, $ticket->tenant_id, $ticket->brand_id);
    }

    public function view(User $user, Message $message): bool
    {
        if (! $user->can('tickets.view')) {
            return false;
        }

        if (! $this->belongsToTenantAndBrand($user, $message->tenant_id, $message->brand_id)) {
            return false;
        }

        if ($message->visibility === Message::VISIBILITY_INTERNAL) {
            return $user->can('tickets.manage');
        }

        return true;
    }

    public function create(User $user, Ticket $ticket): bool
    {
        if (! $user->can('tickets.manage')) {
            return false;
        }

        return $this->belongsToTenantAndBrand($user, $ticket->tenant_id, $ticket->brand_id);
    }

    public function update(User $user, Message $message): bool
    {
        if (! $user->can('tickets.manage')) {
            return false;
        }

        return $this->belongsToTenantAndBrand($user, $message->tenant_id, $message->brand_id);
    }

    public function delete(User $user, Message $message): bool
    {
        if (! $user->can('tickets.manage')) {
            return false;
        }

        return $this->belongsToTenantAndBrand($user, $message->tenant_id, $message->brand_id);
    }

    protected function belongsToTenantAndBrand(User $user, ?int $tenantId, ?int $brandId): bool
    {
        if ($user->tenant_id !== $tenantId) {
            return false;
        }

        if ($brandId === null) {
            return true;
        }

        return $user->brand_id === $brandId;
    }
}
