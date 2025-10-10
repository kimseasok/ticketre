<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreObservabilityStackRequest;
use App\Http\Requests\UpdateObservabilityStackRequest;
use App\Http\Resources\ObservabilityStackResource;
use App\Models\ObservabilityStack;
use App\Services\ObservabilityStackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ObservabilityStackController extends Controller
{
    public function __construct(private readonly ObservabilityStackService $stackService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ObservabilityStack::class);

        $stacks = ObservabilityStack::query()
            ->with(['brand', 'tenant'])
            ->when($request->query('status'), function ($query, $status) {
                $query->where('status', strtolower((string) $status));
            })
            ->when($request->query('brand_id'), function ($query, $brandId) {
                if ($brandId === 'unscoped') {
                    $query->whereNull('brand_id');
                } else {
                    $query->where('brand_id', $brandId);
                }
            })
            ->when($request->query('logs_tool'), function ($query, $tool) {
                $query->where('logs_tool', strtolower((string) $tool));
            })
            ->orderByDesc('updated_at')
            ->paginate();

        return ObservabilityStackResource::collection($stacks);
    }

    public function store(StoreObservabilityStackRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $stack = $this->stackService->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return (new ObservabilityStackResource($stack->loadMissing(['brand', 'tenant'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ObservabilityStack $observabilityStack): ObservabilityStackResource
    {
        $this->authorize('view', $observabilityStack);

        $observabilityStack->load(['brand', 'tenant']);

        return new ObservabilityStackResource($observabilityStack);
    }

    public function update(UpdateObservabilityStackRequest $request, ObservabilityStack $observabilityStack): ObservabilityStackResource
    {
        $this->authorize('update', $observabilityStack);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->stackService->update($observabilityStack, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return new ObservabilityStackResource($updated->loadMissing(['brand', 'tenant']));
    }

    public function destroy(Request $request, ObservabilityStack $observabilityStack): JsonResponse
    {
        try {
            $this->authorize('delete', $observabilityStack);
        } catch (AuthorizationException $exception) {
            return $this->authorizationError($request, $exception);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->stackService->delete($observabilityStack, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    protected function authorizationError(Request $request, AuthorizationException $exception): JsonResponse
    {
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();

        return response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                'correlation_id' => $correlationId,
            ],
        ], Response::HTTP_FORBIDDEN)->header('X-Correlation-ID', $correlationId);
    }
}
