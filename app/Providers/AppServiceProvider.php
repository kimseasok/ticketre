<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('startTime', fn () => now());
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Tenant::observe(TenantObserver::class);
    }
}
