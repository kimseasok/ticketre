<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        Horizon::auth(function ($request) {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (! $user) {
                return false;
            }

            if ($user->hasRole('SuperAdmin')) {
                return true;
            }

            return $user->can('infrastructure.horizon.view');
        });
    }
}
