<?php

namespace App\Policies;

use App\Models\TwoFactorCredential;
use App\Models\User;

class TwoFactorCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('security.2fa.review');
    }

    public function view(User $user, TwoFactorCredential $credential): bool
    {
        if ($user->can('security.2fa.review')) {
            return (int) $credential->tenant_id === (int) $user->tenant_id;
        }

        if ($user->can('security.2fa.manage')) {
            return (int) $credential->user_id === (int) $user->getKey();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('security.2fa.review');
    }

    public function update(User $user, TwoFactorCredential $credential): bool
    {
        if ($user->can('security.2fa.review')) {
            return (int) $credential->tenant_id === (int) $user->tenant_id;
        }

        if ($user->can('security.2fa.manage')) {
            return (int) $credential->user_id === (int) $user->getKey();
        }

        return false;
    }

    public function delete(User $user, TwoFactorCredential $credential): bool
    {
        return $user->can('security.2fa.review')
            && (int) $credential->tenant_id === (int) $user->tenant_id;
    }
}
