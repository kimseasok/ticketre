<?php

namespace App\Services;

use App\Models\PermissionCoverageReport;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoutePermissionCoverageAnalyzer
{
    public function __construct(private readonly Router $router)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function analyze(): array
    {
        $results = [];

        foreach (PermissionCoverageReport::MODULES as $module) {
            $results[$module] = $this->analyzeModule($module);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeModule(string $module): array
    {
        $module = Str::of($module)->lower()->slug('_')->value();
        $routes = $this->moduleRoutes($module);
        $unguarded = [];
        $guarded = 0;

        foreach ($routes as $route) {
            if ($this->isGuarded($route)) {
                $guarded++;
                continue;
            }

            $unguarded[] = [
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'methods' => $route->methods(),
            ];
        }

        $total = $routes->count();
        $unguardedCount = count($unguarded);
        $coverage = $total === 0 ? 100.0 : round((($guarded) / max(1, $total)) * 100, 2);

        return [
            'module' => $module,
            'total_routes' => $total,
            'guarded_routes' => $guarded,
            'unguarded_routes' => $unguardedCount,
            'coverage' => $coverage,
            'unguarded_paths' => collect($unguarded)->map(fn (array $route) => [
                'uri_digest' => hash('sha256', (string) $route['uri']),
                'methods' => $route['methods'],
                'name' => $route['name'],
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, Route>
     */
    protected function moduleRoutes(string $module): Collection
    {
        $routes = collect($this->router->getRoutes());

        return $routes->filter(function (Route $route) use ($module) {
            $uri = Str::of($route->uri())->lower();

            return match ($module) {
                'api' => $uri->startsWith('api/'),
                'portal' => $uri->startsWith('portal/'),
                'admin' => $uri->startsWith(Str::of(config('filament.path', 'admin'))->trim('/').'/')
                    || $uri->exactly(Str::of(config('filament.path', 'admin'))->trim('/')),
                default => false,
            };
        })->reject(function (Route $route) use ($module) {
            if ($module === 'api') {
                return Str::of($route->uri())->lower()->exactly('api/v1/health');
            }

            if ($module === 'admin') {
                $name = $route->getName();

                return $name && Str::startsWith($name, 'filament.admin.auth.');
            }

            return false;
        })->values();
    }

    protected function isGuarded(Route $route): bool
    {
        $middlewares = collect($route->gatherMiddleware())
            ->map(fn ($middleware) => is_string($middleware) ? $middleware : (is_object($middleware) ? $middleware::class : ''))
            ->filter();

        return $middlewares->contains(function (string $middleware): bool {
            $middleware = Str::lower($middleware);

            return Str::startsWith($middleware, 'permission:')
                || Str::startsWith($middleware, 'ability:')
                || Str::startsWith($middleware, 'can:')
                || $middleware === 'auth'
                || Str::startsWith($middleware, 'auth:')
                || $middleware === 'verified'
                || Str::contains($middleware, 'filament');
        });
    }
}
