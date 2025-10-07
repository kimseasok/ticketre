<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBroadcastConnectionRequest;
use App\Http\Requests\UpdateBroadcastConnectionRequest;
use App\Http\Resources\BroadcastConnectionResource;
use App\Models\BroadcastConnection;
use App\Models\User;
use App\Services\BroadcastConnectionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BroadcastConnectionController extends Controller
{
    public function __construct(private readonly BroadcastConnectionService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->guardTenantContext($request);
        $this->authorizeForRequest($request, 'viewAny', BroadcastConnection::class);

        $connections = BroadcastConnection::query()
            ->with(['user', 'brand'])
            ->latest('last_seen_at')
            ->paginate();

        return BroadcastConnectionResource::collection($connections);
    }

    public function store(StoreBroadcastConnectionRequest $request): JsonResponse
    {
        $this->guardTenantContext($request);
        $data = $request->validated();
        $correlationId = Str::limit($request->headers->get('X-Correlation-ID') ?: (string) Str::uuid(), 64, '');

        $data['correlation_id'] = $correlationId;

        $user = $this->resolveUser($request);

        $connection = $this->service->create($data, $user, $correlationId);

        return BroadcastConnectionResource::make($connection->load(['user', 'brand']))
            ->response()
            ->setStatusCode(201)
            ->withHeaders([
                'X-Correlation-ID' => $correlationId,
            ]);
    }

    public function show(Request $request, BroadcastConnection $broadcastConnection): BroadcastConnectionResource
    {
        $this->guardTenantContext($request);
        $this->authorizeForRequest($request, 'view', $broadcastConnection);

        return BroadcastConnectionResource::make($broadcastConnection->load(['user', 'brand']));
    }

    public function update(UpdateBroadcastConnectionRequest $request, BroadcastConnection $broadcastConnection): JsonResponse
    {
        $this->guardTenantContext($request);
        $data = $request->validated();
        $correlationId = Str::limit($request->headers->get('X-Correlation-ID') ?: (string) Str::uuid(), 64, '');

        $data['correlation_id'] = $correlationId;

        $user = $this->resolveUser($request);

        $connection = $this->service->update($broadcastConnection, $data, $user, $correlationId);

        return BroadcastConnectionResource::make($connection->load(['user', 'brand']))
            ->response()
            ->setStatusCode(200)
            ->withHeaders([
                'X-Correlation-ID' => $correlationId,
            ]);
    }

    public function destroy(Request $request, BroadcastConnection $broadcastConnection): JsonResponse
    {
        $this->guardTenantContext($request);
        $this->authorizeForRequest($request, 'delete', $broadcastConnection);

        $correlationId = Str::limit($request->headers->get('X-Correlation-ID') ?: (string) Str::uuid(), 64, '');

        $user = $this->resolveUser($request);

        $this->service->delete($broadcastConnection, $user, $correlationId);

        return response()->noContent()->withHeaders([
            'X-Correlation-ID' => $correlationId,
        ]);
    }

    protected function authorizeForRequest(Request $request, string $ability, mixed $arguments): void
    {
        $user = $this->resolveUser($request);

        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $response->message() ?: 'This action is unauthorized.',
                ],
            ], 403));
        }
    }

    protected function guardTenantContext(Request $request): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $this->resolveUser($request);

        if ($tenant && (int) $user->tenant_id !== (int) $tenant->getKey()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => 'This action is unauthorized for the active tenant.',
                ],
            ], 403));
        }
    }

    protected function resolveUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401));
        }

        return $user;
    }
}
