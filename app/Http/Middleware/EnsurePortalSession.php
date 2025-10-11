<?php

namespace App\Http\Middleware;

use App\Services\PortalSessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnsurePortalSession
{
    public function __construct(private readonly PortalSessionService $sessions)
    {
    }

    public function handle(Request $request, Closure $next, ...$abilities)
    {
        $correlationId = $this->resolveCorrelationId($request->header('X-Correlation-ID'));
        $authorization = trim((string) $request->header('Authorization'));

        if ($authorization === '' || ! str_starts_with($authorization, 'Bearer ')) {
            return $this->deny('missing_token', 401, $correlationId, 'Portal authentication required.');
        }

        $token = trim(substr($authorization, 7));

        try {
            $session = $this->sessions->validateAccessToken(
                $token,
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
                $correlationId
            );
        } catch (AuthenticationException $exception) {
            return $this->deny('invalid_token', 401, $correlationId, $exception->getMessage() ?: 'Portal authentication required.');
        }

        foreach ($abilities as $ability) {
            if ($ability === '') {
                continue;
            }

            if (! in_array($ability, $session->abilities ?? [], true)) {
                Log::warning('portal.session.middleware.denied', [
                    'reason' => 'ability_denied',
                    'portal_session_id' => $session->getKey(),
                    'portal_account_id' => $session->portal_account_id,
                    'tenant_id' => $session->tenant_id,
                    'brand_id' => $session->brand_id,
                    'required_ability' => $ability,
                    'correlation_id' => $correlationId,
                ]);

                return $this->deny('ability_denied', 403, $correlationId, 'This action is unauthorized.');
            }
        }

        $request->attributes->set('portalSession', $session);
        $request->setUserResolver(fn () => $session->account);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if (! $response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }

        return $response;
    }

    protected function deny(string $reason, int $status, string $correlationId, string $message): JsonResponse
    {
        Log::warning('portal.session.middleware.denied', [
            'reason' => $reason,
            'status' => $status,
            'correlation_id' => $correlationId,
        ]);

        $payload = [
            'error' => [
                'code' => $status === 401 ? 'ERR_UNAUTHENTICATED' : 'ERR_HTTP_'.$status,
                'message' => $message,
                'correlation_id' => $correlationId,
            ],
        ];

        return response()->json($payload, $status)->header('X-Correlation-ID', $correlationId);
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $candidate = $value ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }
}
