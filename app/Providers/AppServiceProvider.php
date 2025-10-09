<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Services\KnowledgeBaseContentSanitizer;
use App\Services\TenantPermissionRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('startTime', fn () => now());

        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            $registrar = new TenantPermissionRegistrar($app->make('cache'));

            $registrar->useContext(
                $app->bound('currentTenant') ? $app->make('currentTenant') : null,
                $app->bound('currentBrand') ? $app->make('currentBrand') : null,
            );

            return $registrar;
        });

        $this->app->singleton(KnowledgeBaseContentSanitizer::class, function ($app) {
            $config = $app['config']->get('sanitizer.knowledge_base', []);

            return new KnowledgeBaseContentSanitizer($config);
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Tenant::observe(TenantObserver::class);
    }
}
