<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  $parameters
     */
    public function handle(Request $request, Closure $next, ...$parameters): Response
    {
        $user = $request->user();

        $allowGuest = false;
        $abilities = [];

        foreach ($parameters as $parameter) {
            if ($parameter === 'allow-guest') {
                $allowGuest = true;

                continue;
            }

            if ($parameter !== '') {
                $abilities[] = $parameter;
            }
        }

        if (! $user) {
            if ($allowGuest) {
                $response = $next($request);
                $this->ensureCorrelationIdHeader($request, $response);

                return $response;
            }

            return $this->deny($request, $abilities, 'unauthenticated', 401, null);
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($tenant && (int) $user->tenant_id !== (int) $tenant->getKey()) {
            return $this->deny(
                $request,
                $abilities,
                'tenant_mismatch',
                403,
                $user,
                [
                    'expected_tenant_id' => $user->tenant_id,
                    'resolved_tenant_id' => $tenant->getKey(),
                ]
            );
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($brand && $user->brand_id && (int) $user->brand_id !== (int) $brand->getKey()) {
            return $this->deny(
                $request,
                $abilities,
                'brand_mismatch',
                403,
                $user,
                [
                    'expected_brand_id' => $user->brand_id,
                    'resolved_brand_id' => $brand->getKey(),
                ]
            );
        }

        foreach ($abilities as $ability) {
            if (! $user->can($ability)) {
                return $this->deny($request, $abilities, 'ability_denied', 403, $user);
            }
        }

        $response = $next($request);
        $this->ensureCorrelationIdHeader($request, $response);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function deny(
        Request $request,
        array $abilities,
        string $reason,
        int $status,
        ?User $contextUser,
        array $extra = []
    ): Response {
        $correlationId = $this->correlationId($request);

        Log::warning('rbac.denied', array_merge([
            'correlation_id' => $correlationId,
            'reason' => $reason,
            'user_id' => $contextUser?->getKey(),
            'tenant_id' => $contextUser?->tenant_id,
            'brand_id' => $contextUser?->brand_id,
            'abilities' => $abilities,
            'path' => $request->path(),
            'method' => $request->method(),
        ], $extra));

        $payload = [
            'error' => [
                'code' => $status === 401 ? 'ERR_UNAUTHENTICATED' : 'ERR_HTTP_'.$status,
                'message' => $status === 401
                    ? 'Authentication required.'
                    : ($reason === 'ability_denied'
                        ? 'This action is unauthorized.'
                        : 'Access denied.'),
                'correlation_id' => $correlationId,
            ],
        ];

        if ($request->expectsJson() || str_starts_with(trim($request->path(), '/'), 'api/')) {
            return $this->withCorrelationId(
                response()->json($payload, $status),
                $correlationId
            );
        }

        return $this->withCorrelationId(
            response()->view('errors.403', [
                'message' => $payload['error']['message'],
                'correlationId' => $correlationId,
            ], $status),
            $correlationId
        );
    }

    protected function ensureCorrelationIdHeader(Request $request, Response $response): void
    {
        $correlationId = $request->headers->get('X-Correlation-ID');
        if ($correlationId === null || $correlationId === '') {
            $correlationId = $this->correlationId($request);
        }

        if (! $response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }
    }

    protected function withCorrelationId(Response $response, string $correlationId): Response
    {
        if (! $response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }

        return $response;
    }

    protected function correlationId(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-Correlation-ID'));

        if ($header !== '') {
            return Str::limit($header, 64, '');
        }

        return (string) Str::uuid();
    }
}
