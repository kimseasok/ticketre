<?php

namespace App\Policies;

use App\Models\ObservabilityStack;
use App\Models\User;

class ObservabilityStackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('observability.stacks.view');
    }

    public function view(User $user, ObservabilityStack $stack): bool
    {
        return $user->can('observability.stacks.view')
            && $this->sameTenant($user, $stack)
            && $this->brandAccessible($user, $stack);
    }

    public function create(User $user): bool
    {
        return $user->can('observability.stacks.manage');
    }

    public function update(User $user, ObservabilityStack $stack): bool
    {
        return $user->can('observability.stacks.manage')
            && $this->sameTenant($user, $stack)
            && $this->brandAccessible($user, $stack);
    }

    public function delete(User $user, ObservabilityStack $stack): bool
    {
        return $user->can('observability.stacks.manage')
            && $this->sameTenant($user, $stack)
            && $this->brandAccessible($user, $stack);
    }

    protected function sameTenant(User $user, ObservabilityStack $stack): bool
    {
        return (int) $user->tenant_id === (int) $stack->tenant_id;
    }

    protected function brandAccessible(User $user, ObservabilityStack $stack): bool
    {
        if ($stack->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $stack->brand_id;
    }
}
