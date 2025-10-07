<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Services\KnowledgeBaseContentSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('startTime', fn () => now());

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
