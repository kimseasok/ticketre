<?php

namespace App\Policies;

use App\Models\RedisConfiguration;
use App\Models\User;

class RedisConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('infrastructure.redis.view');
    }

    public function view(User $user, RedisConfiguration $configuration): bool
    {
        return $user->can('infrastructure.redis.view')
            && $this->sameTenant($user, $configuration)
            && $this->brandAccessible($user, $configuration);
    }

    public function create(User $user): bool
    {
        return $user->can('infrastructure.redis.manage');
    }

    public function update(User $user, RedisConfiguration $configuration): bool
    {
        return $user->can('infrastructure.redis.manage')
            && $this->sameTenant($user, $configuration)
            && $this->brandAccessible($user, $configuration);
    }

    public function delete(User $user, RedisConfiguration $configuration): bool
    {
        return $user->can('infrastructure.redis.manage')
            && $this->sameTenant($user, $configuration)
            && $this->brandAccessible($user, $configuration);
    }

    protected function sameTenant(User $user, RedisConfiguration $configuration): bool
    {
        return (int) $user->tenant_id === (int) $configuration->tenant_id;
    }

    protected function brandAccessible(User $user, RedisConfiguration $configuration): bool
    {
        if ($configuration->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $configuration->brand_id;
    }
}
