<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHorizonDeploymentRequest;
use App\Http\Requests\UpdateHorizonDeploymentRequest;
use App\Http\Resources\HorizonDeploymentResource;
use App\Models\HorizonDeployment;
use App\Services\HorizonDeploymentHealthService;
use App\Services\HorizonDeploymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class HorizonDeploymentController extends Controller
{
    public function __construct(
        private readonly HorizonDeploymentService $service,
        private readonly HorizonDeploymentHealthService $healthService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', HorizonDeployment::class);

        $deployments = HorizonDeployment::query()
            ->with(['tenant', 'brand'])
            ->when($request->query('brand_id'), function ($query, $brandId) {
                if ($brandId === 'unscoped') {
                    $query->whereNull('brand_id');
                } else {
                    $query->where('brand_id', $brandId);
                }
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('last_health_status', $status))
            ->when($request->boolean('uses_tls', null), fn ($query, $usesTls) => $query->where('uses_tls', $usesTls))
            ->orderByDesc('updated_at')
            ->paginate();

        return HorizonDeploymentResource::collection($deployments);
    }

    public function store(StoreHorizonDeploymentRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $deployment = $this->service->create($request->validated(), $user, $correlation);

        return (new HorizonDeploymentResource($deployment->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(HorizonDeployment $horizonDeployment): HorizonDeploymentResource
    {
        $this->authorize('view', $horizonDeployment);

        return new HorizonDeploymentResource($horizonDeployment->loadMissing(['tenant', 'brand']));
    }

    public function update(UpdateHorizonDeploymentRequest $request, HorizonDeployment $horizonDeployment): HorizonDeploymentResource
    {
        $this->authorize('update', $horizonDeployment);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $deployment = $this->service->update($horizonDeployment, $request->validated(), $user, $correlation);

        return (new HorizonDeploymentResource($deployment->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]]);
    }

    public function destroy(Request $request, HorizonDeployment $horizonDeployment): JsonResponse
    {
        try {
            $this->authorize('delete', $horizonDeployment);
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
        $this->service->delete($horizonDeployment, $user, $correlation);

        return response()->json(null, Response::HTTP_NO_CONTENT)->header('X-Correlation-ID', $correlation);
    }

    public function health(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HorizonDeployment::class);

        $deployments = HorizonDeployment::query()
            ->when($request->query('brand_id'), function ($query, $brandId) {
                if ($brandId === 'unscoped') {
                    $query->whereNull('brand_id');
                } else {
                    $query->where('brand_id', $brandId);
                }
            })
            ->get();

        $correlation = $this->correlationId($request);
        $summary = $this->healthService->summarize($deployments, $correlation);

        return response()
            ->json([
                'status' => $summary['status'],
                'deployments' => $summary['deployments'],
                'meta' => ['correlation_id' => $summary['correlation_id']],
            ])
            ->header('X-Correlation-ID', $summary['correlation_id']);
    }

    public function showHealth(Request $request, HorizonDeployment $horizonDeployment): JsonResponse
    {
        $this->authorize('view', $horizonDeployment);

        $correlation = $this->correlationId($request);
        $result = $this->healthService->check($horizonDeployment, $correlation);

        return response()
            ->json([
                'status' => $result['status'],
                'report' => $result['report'],
                'meta' => ['correlation_id' => $result['correlation_id']],
            ])
            ->header('X-Correlation-ID', $result['correlation_id']);
    }

    protected function correlationId(Request $request): string
    {
        $value = $request->header('X-Correlation-ID') ?? $request->query('correlation_id');
        $value = trim((string) $value);

        if ($value !== '') {
            return str($value)->limit(64, '');
        }

        return (string) str()->uuid();
    }
}
