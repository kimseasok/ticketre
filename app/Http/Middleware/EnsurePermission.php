<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccessAttemptLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly AccessAttemptLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $permissions = $this->normalizePermissions($abilities);
        $user = $request->user();

        if (! $user) {
            $this->logger->log($request, null, $permissions[0] ?? 'unknown', 'unauthenticated', false);

            return $this->unauthorizedResponse($request, 401, 'Authentication required.', 'ERR_UNAUTHENTICATED');
        }

        $user->loadMissing('tenant', 'brand');

        if ($reason = $this->detectTenantMismatch($request, $user)) {
            $this->logger->log($request, $user, $permissions[0] ?? 'unknown', $reason, false);

            return $this->unauthorizedResponse($request, 403, 'Tenant context mismatch.', 'ERR_HTTP_403');
        }

        if ($reason = $this->detectBrandMismatch($request, $user)) {
            $this->logger->log($request, $user, $permissions[0] ?? 'unknown', $reason, false);

            return $this->unauthorizedResponse($request, 403, 'Brand context mismatch.', 'ERR_HTTP_403');
        }

        foreach ($permissions as $permission) {
            if ($permission === '' || $user->can($permission)) {
                return $next($request);
            }
        }

        $this->logger->log($request, $user, $permissions[0] ?? 'unknown', 'insufficient_permission', false, [
            'context' => 'middleware',
        ]);

        return $this->unauthorizedResponse($request, 403, 'This action is unauthorized.', 'ERR_HTTP_403');
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    protected function normalizePermissions(array $abilities): array
    {
        if (empty($abilities)) {
            return [''];
        }

        $flattened = [];
        foreach ($abilities as $ability) {
            foreach (preg_split('/[|,]/', $ability) ?: [] as $part) {
                $flattened[] = trim($part);
            }
        }

        return array_values(array_filter(array_unique($flattened)));
    }

    protected function detectTenantMismatch(Request $request, User $user): ?string
    {
        if (app()->bound('currentTenant') && app('currentTenant')) {
            if ((int) $user->tenant_id !== (int) app('currentTenant')->getKey()) {
                return 'tenant_mismatch';
            }
        }

        $headerTenant = $request->headers->get('X-Tenant');
        if ($headerTenant && $user->tenant && ! hash_equals($user->tenant->slug, (string) $headerTenant)) {
            return 'tenant_mismatch';
        }

        return null;
    }

    protected function detectBrandMismatch(Request $request, User $user): ?string
    {
        $currentBrand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($currentBrand && $user->brand_id && (int) $user->brand_id !== (int) $currentBrand->getKey()) {
            return 'brand_mismatch';
        }

        $headerBrand = $request->headers->get('X-Brand');
        if ($headerBrand && $user->brand && ! hash_equals($user->brand->slug, (string) $headerBrand)) {
            return 'brand_mismatch';
        }

        return null;
    }

    protected function unauthorizedResponse(Request $request, int $status, string $message, string $code): JsonResponse|Response
    {
        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ], $status);
        }

        abort($status, $message);
    }

    protected function shouldReturnJson(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        $path = '/'.ltrim($request->path(), '/');

        return Str::startsWith($path, '/api/');
    }
}
