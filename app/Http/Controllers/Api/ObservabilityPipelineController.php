<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreObservabilityPipelineRequest;
use App\Http\Requests\UpdateObservabilityPipelineRequest;
use App\Http\Resources\ObservabilityPipelineResource;
use App\Models\ObservabilityPipeline;
use App\Services\ObservabilityPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ObservabilityPipelineController extends Controller
{
    public function __construct(private readonly ObservabilityPipelineService $pipelineService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ObservabilityPipeline::class);

        $pipelines = ObservabilityPipeline::query()
            ->with(['brand', 'tenant'])
            ->when($request->query('pipeline_type'), fn ($query, $type) => $query->where('pipeline_type', $type))
            ->when($request->query('brand_id'), function ($query, $brandId) {
                if ($brandId === 'unscoped') {
                    $query->whereNull('brand_id');
                } else {
                    $query->where('brand_id', $brandId);
                }
            })
            ->orderByDesc('updated_at')
            ->paginate();

        return ObservabilityPipelineResource::collection($pipelines);
    }

    public function store(StoreObservabilityPipelineRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $pipeline = $this->pipelineService->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return (new ObservabilityPipelineResource($pipeline->loadMissing(['brand', 'tenant'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ObservabilityPipeline $observabilityPipeline): ObservabilityPipelineResource
    {
        $this->authorize('view', $observabilityPipeline);

        $observabilityPipeline->load(['brand', 'tenant']);

        return new ObservabilityPipelineResource($observabilityPipeline);
    }

    public function update(UpdateObservabilityPipelineRequest $request, ObservabilityPipeline $observabilityPipeline): ObservabilityPipelineResource
    {
        $this->authorize('update', $observabilityPipeline);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->pipelineService->update($observabilityPipeline, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return new ObservabilityPipelineResource($updated->loadMissing(['brand', 'tenant']));
    }

    public function destroy(Request $request, ObservabilityPipeline $observabilityPipeline): JsonResponse
    {
        $this->authorize('delete', $observabilityPipeline);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->pipelineService->delete($observabilityPipeline, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
