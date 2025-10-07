<?php

namespace App\Providers;

use App\Services\TenantPermissionRegistrar;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class PermissionRegistrarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return tap(new TenantPermissionRegistrar($app['cache']), function (TenantPermissionRegistrar $registrar) {
                $registrar->initializeCache();
            });
        });
    }
}
