<?php

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BindAuthenticatedTenant;

class FilamentServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(env('FILAMENT_PATH', 'admin'))
            ->login()
            ->brandName(env('FILAMENT_BRAND', config('app.name')))
            ->colors([
                'primary' => Color::hex('#2563eb'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                'web',
            ])
            ->authMiddleware([
                Authenticate::class,
                BindAuthenticatedTenant::class,
                EnsurePermission::class.':admin.access',
            ])
            ->authGuard('web')
            ->sidebarCollapsibleOnDesktop();
    }
}
