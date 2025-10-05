<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\User;

class KbArticlePolicy
{
    public function view(User $user, KbArticle $kbArticle): bool
    {
        return $user->can('knowledge.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge.manage');
    }

    public function update(User $user, KbArticle $kbArticle): bool
    {
        return $user->can('knowledge.manage');
    }

    public function delete(User $user, KbArticle $kbArticle): bool
    {
        return $user->can('knowledge.manage');
    }
}
