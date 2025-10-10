<?php

namespace App\Policies;

use App\Models\BrandAsset;
use App\Models\User;

class BrandAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('brand_assets.view');
    }

    public function view(User $user, BrandAsset $asset): bool
    {
        return $user->can('brand_assets.view')
            && $this->sameTenant($user, $asset)
            && $this->brandAccessible($user, $asset);
    }

    public function create(User $user): bool
    {
        return $user->can('brand_assets.manage');
    }

    public function update(User $user, BrandAsset $asset): bool
    {
        return $user->can('brand_assets.manage')
            && $this->sameTenant($user, $asset)
            && $this->brandAccessible($user, $asset);
    }

    public function delete(User $user, BrandAsset $asset): bool
    {
        return $user->can('brand_assets.manage')
            && $this->sameTenant($user, $asset)
            && $this->brandAccessible($user, $asset);
    }

    protected function sameTenant(User $user, BrandAsset $asset): bool
    {
        return (int) $user->tenant_id === (int) $asset->tenant_id;
    }

    protected function brandAccessible(User $user, BrandAsset $asset): bool
    {
        if ($user->brand_id === null) {
            return true;
        }

        return (int) $user->brand_id === (int) $asset->brand_id;
    }
}
