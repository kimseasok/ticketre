<?php

namespace App\Http\Middleware;

use App\Models\TwoFactorCredential;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        if ($request->routeIs('api.two-factor.*')) {
            return $this->ensureCorrelationHeader($request, $next($request));
        }

        $user = $request->user();

        if (! $user) {
            return $this->ensureCorrelationHeader($request, $next($request));
        }

        if ($user->hasRole('SuperAdmin')) {
            return $this->ensureCorrelationHeader($request, $next($request));
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $settings = $tenant?->settings ?? [];

        $enforced = (bool) data_get($settings, 'security.two_factor.enforced', true);
        $requiredRoles = (array) data_get($settings, 'security.two_factor.required_roles', ['Admin', 'Agent']);

        if (! $enforced || ! $user->hasAnyRole($requiredRoles)) {
            return $this->ensureCorrelationHeader($request, $next($request));
        }

        $credential = $this->credentialForUser($user);
        $correlationId = $this->correlationId($request);

        if (! $credential || ! $credential->isConfirmed()) {
            return $this->deny($request, $correlationId, 'ERR_2FA_NOT_CONFIRMED', 'Two-factor authentication setup is incomplete.', Response::HTTP_FORBIDDEN);
        }

        if ($credential->isLocked()) {
            return $this->deny($request, $correlationId, 'ERR_2FA_LOCKED', 'Two-factor authentication is temporarily locked.', Response::HTTP_LOCKED, [
                'locked_until' => $credential->locked_until?->toAtomString(),
            ]);
        }

        $sessionKey = $this->sessionKey($user->getKey());
        $verifiedUntil = $request->session()->get($sessionKey);

        if (! $verifiedUntil) {
            return $this->deny($request, $correlationId, 'ERR_2FA_REQUIRED', 'Two-factor authentication challenge required.', Response::HTTP_PRECONDITION_REQUIRED);
        }

        $expiresAt = Carbon::parse($verifiedUntil);
        if ($expiresAt->isPast()) {
            $request->session()->forget($sessionKey);

            return $this->deny($request, $correlationId, 'ERR_2FA_REQUIRED', 'Two-factor authentication challenge required.', Response::HTTP_PRECONDITION_REQUIRED);
        }

        return $this->ensureCorrelationHeader($request, $next($request), $correlationId);
    }

    private function deny(Request $request, string $correlationId, string $code, string $message, int $status, array $context = []): JsonResponse
    {
        Log::warning('two_factor.enforcement_denied', [
            'user_id' => $request->user()?->getKey(),
            'tenant_id' => $request->user()?->tenant_id,
            'brand_id' => $request->user()?->brand_id,
            'code' => $code,
            'status' => $status,
            'correlation_id' => $correlationId,
        ] + $context);

        $payload = [
            'error' => array_merge([
                'code' => $code,
                'message' => $message,
                'correlation_id' => $correlationId,
            ], $context ? ['context' => $context] : []),
        ];

        return response()
            ->json($payload, $status)
            ->header('X-Correlation-ID', $correlationId);
    }

    private function ensureCorrelationHeader(Request $request, Response $response, ?string $correlationId = null): Response
    {
        $correlationId = $correlationId ?? $this->correlationId($request);

        if (! $response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }

        return $response;
    }

    private function correlationId(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-Correlation-ID'));

        if ($header !== '') {
            return $header;
        }

        return (string) str()->uuid();
    }

    private function sessionKey(int $userId): string
    {
        return 'two_factor_verified_'.$userId;
    }

    private function credentialForUser(User $user): ?TwoFactorCredential
    {
        return TwoFactorCredential::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->getKey())
            ->first();
    }
}
