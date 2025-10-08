<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $host = $request->getHost();
        $headerTenant = $request->headers->get('X-Tenant');

        $tenant = Tenant::query()
            ->where('domain', $host)
            ->orWhere('slug', $headerTenant)
            ->first();

        if (! $tenant) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_TENANT_NOT_FOUND',
                    'message' => 'Tenant could not be resolved.',
                ],
            ], 404);
        }

        $brand = null;
        if ($request->headers->has('X-Brand')) {
            $brand = $tenant->brands()->where('slug', $request->headers->get('X-Brand'))->first();
        }

        app()->instance(Tenant::class, $tenant);
        app()->instance('currentTenant', $tenant);
        app()->instance('currentBrand', $brand);

        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();
        if (method_exists($permissionRegistrar, 'clearPermissionsCollection')) {
            $permissionRegistrar->clearPermissionsCollection();
        }

        return $next($request);
    }
}
