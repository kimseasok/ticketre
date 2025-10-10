<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('brands.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can('brands.view')
            && $this->sameTenant($user, $brand)
            && $this->brandAccessible($user, $brand);
    }

    public function create(User $user): bool
    {
        return $user->can('brands.manage');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can('brands.manage')
            && $this->sameTenant($user, $brand)
            && $this->brandAccessible($user, $brand);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('brands.manage')
            && $this->sameTenant($user, $brand)
            && $this->brandAccessible($user, $brand);
    }

    protected function sameTenant(User $user, Brand $brand): bool
    {
        return (int) $user->tenant_id === (int) $brand->tenant_id;
    }

    protected function brandAccessible(User $user, Brand $brand): bool
    {
        if ($user->brand_id === null) {
            return true;
        }

        return (int) $user->brand_id === (int) $brand->getKey();
    }
}
