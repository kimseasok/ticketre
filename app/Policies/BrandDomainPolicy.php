<?php

namespace App\Policies;

use App\Models\BrandDomain;
use App\Models\User;

class BrandDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('brand_domains.view');
    }

    public function view(User $user, BrandDomain $brandDomain): bool
    {
        return $user->can('brand_domains.view')
            && $this->sameTenant($user, $brandDomain)
            && $this->sameBrand($user, $brandDomain);
    }

    public function create(User $user): bool
    {
        return $user->can('brand_domains.manage');
    }

    public function update(User $user, BrandDomain $brandDomain): bool
    {
        return $user->can('brand_domains.manage')
            && $this->sameTenant($user, $brandDomain)
            && $this->sameBrand($user, $brandDomain);
    }

    public function delete(User $user, BrandDomain $brandDomain): bool
    {
        return $user->can('brand_domains.manage')
            && $this->sameTenant($user, $brandDomain)
            && $this->sameBrand($user, $brandDomain);
    }

    public function verify(User $user, BrandDomain $brandDomain): bool
    {
        return $this->update($user, $brandDomain);
    }

    protected function sameTenant(User $user, BrandDomain $brandDomain): bool
    {
        return (int) $user->tenant_id === (int) $brandDomain->tenant_id;
    }

    protected function sameBrand(User $user, BrandDomain $brandDomain): bool
    {
        if ($user->brand_id === null) {
            return true;
        }

        return (int) $user->brand_id === (int) $brandDomain->brand_id;
    }
}
