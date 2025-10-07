<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BroadcastAuthRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BroadcastAuthController extends Controller
{
    public function __construct(private readonly BroadcastManager $broadcastManager)
    {
    }

    public function __invoke(BroadcastAuthRequest $request): JsonResponse
    {
        $correlationId = Str::limit($request->headers->get('X-Correlation-ID') ?: (string) Str::uuid(), 64, '');

        $user = $this->resolveUser($request, $correlationId);

        $this->guardChannelAccess($request, $user, $correlationId);

        try {
            $response = $this->broadcastManager->auth($request);
        } catch (AuthorizationException $exception) {
            $this->logAttempt('broadcast.auth.failed', $request, $user, $correlationId, $exception->getMessage());

            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                ],
            ], 403, [
                'X-Correlation-ID' => $correlationId,
            ]));
        } catch (\Throwable $exception) {
            $this->logAttempt('broadcast.auth.failed', $request, $user, $correlationId, $exception->getMessage());

            $status = $exception instanceof HttpResponseException ? $exception->getStatusCode() : 403;
            $code = $status === 404 ? 'ERR_HTTP_404' : 'ERR_HTTP_403';
            $message = $status === 404 ? 'Channel not found.' : 'This action is unauthorized.';

            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ], $status, [
                'X-Correlation-ID' => $correlationId,
            ]));
        }

        if ($response instanceof Response) {
            $payload = $this->responsePayload($response);
            $status = $response->getStatusCode();
        } else {
            $payload = [
                'auth' => 'log-driver',
                'channel_data' => null,
            ];
            $status = 200;
        }

        $this->logAttempt('broadcast.auth.succeeded', $request, $user, $correlationId, null);

        return response()->json(array_merge($payload, [
            'correlation_id' => $correlationId,
        ]), $status, [
            'X-Correlation-ID' => $correlationId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function responsePayload(Response $response): array
    {
        $content = $response->getContent();

        if ($content === false || $content === null || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function logAttempt(string $event, BroadcastAuthRequest $request, User $user, string $correlationId, ?string $error): void
    {
        $channelName = (string) $request->input('channel_name');
        $socketId = (string) $request->input('socket_id');
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;

        Log::channel(config('logging.default'))->info($event, [
            'channel' => Str::limit($channelName, 255, ''),
            'socket_digest' => hash('sha256', $socketId),
            'tenant_id' => $tenant?->getKey(),
            'brand_id' => $brand?->getKey(),
            'user_id' => $user->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'broadcast_auth',
            'error' => $error ? Str::limit($error, 255, '') : null,
        ]);
    }

    protected function guardChannelAccess(BroadcastAuthRequest $request, User $user, string $correlationId): void
    {
        $channel = (string) $request->input('channel_name');
        $normalized = Str::startsWith($channel, 'private-') ? Str::after($channel, 'private-') : $channel;

        if (! preg_match('/^tenants\.(\d+)\.brands\.(\d+)\.tickets$/', $normalized, $matches)) {
            return;
        }

        $tenantId = (int) ($matches[1] ?? 0);
        $brandId = (int) ($matches[2] ?? 0);
        if ((int) $user->tenant_id !== $tenantId) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => 'This action is unauthorized for the active tenant.',
                ],
            ], 403, [
                'X-Correlation-ID' => $correlationId,
            ]));
        }

        if ($brandId !== 0 && $user->brand_id !== null && (int) $user->brand_id !== $brandId) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => 'This action is unauthorized for the active brand.',
                ],
            ], 403, [
                'X-Correlation-ID' => $correlationId,
            ]));
        }

        $hasViewPermission = $user->can('tickets.view')
            || $user->roles()->whereHas('permissions', fn ($query) => $query->where('name', 'tickets.view'))->exists();

        if (! $hasViewPermission) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => 'This action is unauthorized.',
                ],
            ], 403, [
                'X-Correlation-ID' => $correlationId,
            ]));
        }
    }

    protected function resolveUser(BroadcastAuthRequest $request, string $correlationId): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401, [
                'X-Correlation-ID' => $correlationId,
            ]));
        }

        return $user;
    }
}
