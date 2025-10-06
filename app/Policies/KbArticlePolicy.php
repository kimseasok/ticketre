<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\User;

class KbArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('knowledge.view') || $user->can('knowledge.manage');
    }

    public function view(User $user, KbArticle $kbArticle): bool
    {
        return $this->matchesScope($user, $kbArticle)
            && ($user->can('knowledge.view') || $user->can('knowledge.manage'));
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge.manage');
    }

    public function update(User $user, KbArticle $kbArticle): bool
    {
        return $this->canManage($user, $kbArticle);
    }

    public function delete(User $user, KbArticle $kbArticle): bool
    {
        return $this->canManage($user, $kbArticle);
    }

    protected function matchesScope(User $user, KbArticle $kbArticle): bool
    {
        if ($user->tenant_id !== $kbArticle->tenant_id) {
            return false;
        }

        if ($user->brand_id && $kbArticle->brand_id && $user->brand_id !== $kbArticle->brand_id) {
            return false;
        }

        return true;
    }

    protected function canManage(User $user, KbArticle $kbArticle): bool
    {
        return $user->can('knowledge.manage') && $this->matchesScope($user, $kbArticle);
    }
}
