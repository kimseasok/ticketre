<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait HandlesAuthorization
{
    protected static function userCan(string $permission): bool
    {
        $user = Auth::user();

        return $user ? $user->can($permission) : false;
    }
}
