<?php

namespace App\Policies;

use App\Models\PortalSession;
use App\Models\User;

class PortalSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('portal.sessions.view');
    }

    public function view(User $user, PortalSession $session): bool
    {
        return $user->can('portal.sessions.view')
            && $this->sameTenant($user, $session)
            && $this->brandAccessible($user, $session);
    }

    public function delete(User $user, PortalSession $session): bool
    {
        return $user->can('portal.sessions.manage')
            && $this->sameTenant($user, $session)
            && $this->brandAccessible($user, $session);
    }

    protected function sameTenant(User $user, PortalSession $session): bool
    {
        return (int) $user->tenant_id === (int) $session->tenant_id;
    }

    protected function brandAccessible(User $user, PortalSession $session): bool
    {
        if ($session->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $session->brand_id;
    }
}
