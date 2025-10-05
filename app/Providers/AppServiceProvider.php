<?php

namespace App\Providers;

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
    }
}
