<?php

namespace App\Policies;

use App\Models\SmtpOutboundMessage;
use App\Models\User;

class SmtpOutboundMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('email.dispatches.view');
    }

    public function view(User $user, SmtpOutboundMessage $message): bool
    {
        return $user->can('email.dispatches.view')
            && $this->sameTenant($user, $message)
            && $this->brandAccessible($user, $message);
    }

    public function create(User $user): bool
    {
        return $user->can('email.dispatches.manage');
    }

    public function update(User $user, SmtpOutboundMessage $message): bool
    {
        return $user->can('email.dispatches.manage')
            && $this->sameTenant($user, $message)
            && $this->brandAccessible($user, $message);
    }

    public function delete(User $user, SmtpOutboundMessage $message): bool
    {
        return $user->can('email.dispatches.manage')
            && $this->sameTenant($user, $message)
            && $this->brandAccessible($user, $message);
    }

    public function retry(User $user, SmtpOutboundMessage $message): bool
    {
        return $user->can('email.dispatches.manage')
            && $this->sameTenant($user, $message)
            && $this->brandAccessible($user, $message);
    }

    protected function sameTenant(User $user, SmtpOutboundMessage $message): bool
    {
        return (int) $user->tenant_id === (int) $message->tenant_id;
    }

    protected function brandAccessible(User $user, SmtpOutboundMessage $message): bool
    {
        if ($user->brand_id === null) {
            return true;
        }

        return (int) $user->brand_id === (int) $message->brand_id;
    }
}
