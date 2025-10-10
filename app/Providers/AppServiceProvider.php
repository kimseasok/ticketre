<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Cache\RedisFallbackStore;
use App\Services\KnowledgeBaseContentSanitizer;
use App\Services\ObservabilityMetricRecorder;
use App\Services\TenantPermissionRegistrar;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
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

        $this->app->singleton(ObservabilityMetricRecorder::class, function ($app) {
            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);
            $store = $cacheFactory->store($app['config']->get('observability.metrics_cache_store', 'array'));

            return new ObservabilityMetricRecorder($store);
        });

    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Tenant::observe(TenantObserver::class);

        Cache::extend('redis_fallback', function ($app, array $config) {
            $connection = $config['connection'] ?? 'cache';
            $prefix = $config['prefix'] ?? $app['config']->get('cache.prefix', 'laravel_cache');

            $primaryStore = new RedisStore($app->make('redis'), $prefix, $connection);

            $fallbackName = $config['fallback'] ?? 'file';

            if ($fallbackName === 'file') {
                $path = $app['config']->get('cache.stores.file.path', storage_path('framework/cache/data'));
                $fallbackStore = new FileStore($app['files'], $path);
            } else {
                $fallbackName = 'array';
                $fallbackStore = new ArrayStore();
            }

            $host = (string) data_get($app['config']->get('database.redis'), $connection.'.host', '127.0.0.1');
            $port = (int) data_get($app['config']->get('database.redis'), $connection.'.port', 6379);
            $hostDigest = hash('sha256', $host.':'.$port);

            $store = new RedisFallbackStore($primaryStore, $fallbackStore, $fallbackName, $connection, $hostDigest);

            return new CacheRepository($store);
        });

        Session::extend('redis-fallback', function ($app) {
            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);
            $storeName = $app['config']->get('session.store', 'redis-fallback');
            $store = $cacheFactory->store($storeName);
            $lifetime = (int) $app['config']->get('session.lifetime', 120);

            return new CacheBasedSessionHandler($store, $lifetime, $app);
        });
    }
}
