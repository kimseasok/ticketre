<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\StoreRedisConfigurationRequest;
use App\Http\Requests\UpdateRedisConfigurationRequest;
use App\Http\Resources\RedisConfigurationResource;
use App\Models\RedisConfiguration;
use App\Services\RedisConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RedisConfigurationController extends Controller
{
    public function __construct(private readonly RedisConfigurationService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RedisConfiguration::class);

        $configurations = RedisConfiguration::query()
            ->with(['tenant', 'brand'])
            ->when($request->query('brand_id'), function ($query, $brandId) {
                if ($brandId === 'unscoped') {
                    $query->whereNull('brand_id');
                } else {
                    $query->where('brand_id', $brandId);
                }
            })
            ->when($request->query('active'), fn ($query, $active) => $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('updated_at')
            ->paginate();

        return RedisConfigurationResource::collection($configurations);
    }

    public function store(StoreRedisConfigurationRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);
        $configuration = $this->service->create($request->validated(), $user, $correlation);

        return (new RedisConfigurationResource($configuration->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(RedisConfiguration $redisConfiguration): RedisConfigurationResource
    {
        $this->authorize('view', $redisConfiguration);

        $redisConfiguration->load(['tenant', 'brand']);

        return new RedisConfigurationResource($redisConfiguration);
    }

    public function update(UpdateRedisConfigurationRequest $request, RedisConfiguration $redisConfiguration): RedisConfigurationResource
    {
        $this->authorize('update', $redisConfiguration);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);
        $configuration = $this->service->update($redisConfiguration, $request->validated(), $user, $correlation);

        return (new RedisConfigurationResource($configuration->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]]);
    }

    public function destroy(Request $request, RedisConfiguration $redisConfiguration): JsonResponse
    {
        try {
            $this->authorize('delete', $redisConfiguration);
        } catch (AuthorizationException $exception) {
            $correlation = $this->correlationId($request);

            return response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                    'correlation_id' => $correlation,
                ],
            ], Response::HTTP_FORBIDDEN)->header('X-Correlation-ID', $correlation);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);
        $this->service->delete($redisConfiguration, $user, $correlation);

        return response()->json(null, Response::HTTP_NO_CONTENT)->header('X-Correlation-ID', $correlation);
    }

    protected function correlationId(Request $request): string
    {
        $value = $request->header('X-Correlation-ID') ?? (string) str()->uuid();

        return str($value)->limit(64, '');
    }
}
