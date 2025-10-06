<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('messages.view');
    }

    public function view(User $user, Message $message): bool
    {
        return $user->can('messages.view')
            && $user->tenant_id === $message->tenant_id
            && ($message->brand_id === null || $user->brand_id === $message->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->can('messages.manage');
    }

    public function update(User $user, Message $message): bool
    {
        return $user->can('messages.manage')
            && $user->tenant_id === $message->tenant_id
            && ($message->brand_id === null || $user->brand_id === $message->brand_id);
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->can('messages.manage')
            && $user->tenant_id === $message->tenant_id
            && ($message->brand_id === null || $user->brand_id === $message->brand_id);
    }
}
