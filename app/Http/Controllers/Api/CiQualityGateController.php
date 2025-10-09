<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCiQualityGateRequest;
use App\Http\Requests\UpdateCiQualityGateRequest;
use App\Http\Resources\CiQualityGateResource;
use App\Models\CiQualityGate;
use App\Services\CiQualityGateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CiQualityGateController extends Controller
{
    public function __construct(private readonly CiQualityGateService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', CiQualityGate::class);

        $query = CiQualityGate::query()
            ->with(['tenant', 'brand'])
            ->when($request->query('brand'), function ($builder, $brand) {
                $builder->whereHas('brand', fn ($q) => $q->where('slug', $brand));
            })
            ->when($request->query('search'), function ($builder, $search) {
                $like = '%'.$search.'%';
                $builder->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
            })
            ->orderByDesc('updated_at');

        $gates = $query->paginate(25)->appends($request->query());

        return CiQualityGateResource::collection($gates);
    }

    public function store(StoreCiQualityGateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $correlation = $data['correlation_id'] ?? $request->header('X-Correlation-ID') ?? (string) Str::uuid();
        $gate = $this->service->create($data, $request->user(), $correlation);

        return CiQualityGateResource::make($gate->load(['tenant', 'brand']))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CiQualityGate $ciQualityGate): JsonResponse
    {
        $this->authorizeRequest($request, 'view', $ciQualityGate);

        $resource = CiQualityGateResource::make($ciQualityGate->loadMissing(['tenant', 'brand']))
            ->additional(['meta' => ['correlation_id' => $request->header('X-Correlation-ID')]]);

        return $resource->response();
    }

    public function update(UpdateCiQualityGateRequest $request, CiQualityGate $ciQualityGate): JsonResponse
    {
        $data = $request->validated();
        $correlation = $data['correlation_id'] ?? $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        $gate = $this->service->update($ciQualityGate, $data, $request->user(), $correlation);

        $resource = CiQualityGateResource::make($gate->loadMissing(['tenant', 'brand']))
            ->additional(['meta' => ['correlation_id' => $correlation]]);

        return $resource->response();
    }

    public function destroy(Request $request, CiQualityGate $ciQualityGate): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $ciQualityGate);

        $correlation = $request->header('X-Correlation-ID') ?? (string) Str::uuid();
        $this->service->delete($ciQualityGate, $request->user(), $correlation);

        return response()->json(null, 204);
    }

    protected function authorizeRequest(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401));
        }

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
}
