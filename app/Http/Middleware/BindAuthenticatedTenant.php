<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class BindAuthenticatedTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            app()->instance('currentTenant', $request->user()->tenant);
            app()->instance('currentBrand', $request->user()->brand);

            $permissionRegistrar = app(PermissionRegistrar::class);
            $permissionRegistrar->forgetCachedPermissions();
            if (method_exists($permissionRegistrar, 'clearPermissionsCollection')) {
                $permissionRegistrar->clearPermissionsCollection();
            }
        }

        return $next($request);
    }
}
