<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindAuthenticatedTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            app()->instance('currentTenant', $request->user()->tenant);
            app()->instance('currentBrand', $request->user()->brand);
        }

        return $next($request);
    }
}
