<?php

namespace App\Policies;

use App\Models\KbCategory;
use App\Models\User;

class KbCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('knowledge.view') || $user->can('knowledge.manage');
    }

    public function view(User $user, KbCategory $category): bool
    {
        return $this->matchesScope($user, $category)
            && ($user->can('knowledge.view') || $user->can('knowledge.manage'));
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge.manage');
    }

    public function update(User $user, KbCategory $category): bool
    {
        return $this->canManage($user, $category);
    }

    public function delete(User $user, KbCategory $category): bool
    {
        return $this->canManage($user, $category);
    }

    protected function matchesScope(User $user, KbCategory $category): bool
    {
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        if ($user->brand_id && $category->brand_id && $user->brand_id !== $category->brand_id) {
            return false;
        }

        return true;
    }

    protected function canManage(User $user, KbCategory $category): bool
    {
        return $user->can('knowledge.manage') && $this->matchesScope($user, $category);
    }
}
