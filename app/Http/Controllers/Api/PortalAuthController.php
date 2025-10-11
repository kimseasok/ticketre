<?php

namespace App\Http\Controllers\Api;

use App\Data\PortalSessionTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\PortalLoginRequest;
use App\Http\Requests\PortalLogoutRequest;
use App\Http\Requests\PortalRefreshRequest;
use App\Http\Resources\PortalSessionResource;
use App\Http\Resources\PortalSessionTokenResource;
use App\Models\PortalSession;
use App\Services\PortalSessionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    public function __construct(private readonly PortalSessionService $sessions)
    {
    }

    public function login(PortalLoginRequest $request): JsonResponse
    {
        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        try {
            $tokens = $this->sessions->login(
                $request->validated(),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
                $correlationId
            );
        } catch (AuthenticationException $exception) {
            return $this->errorResponse('ERR_UNAUTHENTICATED', $exception->getMessage() ?: 'Portal authentication required.', 401, $correlationId);
        }

        return $this->tokenResponse($tokens);
    }

    public function refresh(PortalRefreshRequest $request): JsonResponse
    {
        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        try {
            $tokens = $this->sessions->refresh(
                (string) $request->input('refresh_token'),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
                $correlationId,
                $request->input('device_name')
            );
        } catch (AuthenticationException $exception) {
            return $this->errorResponse('ERR_UNAUTHENTICATED', $exception->getMessage() ?: 'Portal authentication required.', 401, $correlationId);
        }

        return $this->tokenResponse($tokens);
    }

    public function logout(PortalLogoutRequest $request): Response
    {
        /** @var PortalSession|null $session */
        $session = $request->attributes->get('portalSession');

        if (! $session instanceof PortalSession) {
            throw new AuthenticationException('Portal session missing.');
        }

        $correlationId = $this->sessions->logout(
            $session,
            $request->ip() ?? '127.0.0.1',
            $request->userAgent(),
            $request->header('X-Correlation-ID')
        );

        $response = response(null, 204);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    public function session(PortalLogoutRequest $request): JsonResponse
    {
        /** @var PortalSession|null $session */
        $session = $request->attributes->get('portalSession');

        if (! $session instanceof PortalSession) {
            throw new AuthenticationException('Portal session missing.');
        }

        $session->loadMissing('account');
        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        $resource = PortalSessionResource::make($session)->additional([
            'meta' => ['correlation_id' => $correlationId],
        ]);

        $response = $resource->response();
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    public function abilities(PortalLogoutRequest $request): JsonResponse
    {
        /** @var PortalSession|null $session */
        $session = $request->attributes->get('portalSession');

        if (! $session instanceof PortalSession) {
            throw new AuthenticationException('Portal session missing.');
        }

        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        return response()->json([
            'data' => [
                'abilities' => array_values($session->abilities ?? []),
            ],
            'meta' => [
                'correlation_id' => $correlationId,
            ],
        ])->header('X-Correlation-ID', $correlationId);
    }

    protected function tokenResponse(PortalSessionTokens $tokens): JsonResponse
    {
        $resource = PortalSessionTokenResource::make($tokens)->additional([
            'meta' => [
                'correlation_id' => $tokens->correlationId,
            ],
        ]);

        $response = $resource->response();
        $response->headers->set('X-Correlation-ID', $tokens->correlationId);

        return $response;
    }

    protected function resolveCorrelation(?string $value): string
    {
        $candidate = $value ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function errorResponse(string $code, string $message, int $status, string $correlationId): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'correlation_id' => $correlationId,
            ],
        ], $status)->header('X-Correlation-ID', $correlationId);
    }
}
