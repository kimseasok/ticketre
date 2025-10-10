<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRbacEnforcementGapAnalysisRequest;
use App\Http\Requests\UpdateRbacEnforcementGapAnalysisRequest;
use App\Http\Resources\RbacEnforcementGapAnalysisResource;
use App\Models\RbacEnforcementGapAnalysis;
use App\Services\RbacEnforcementGapAnalysisService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RbacEnforcementGapAnalysisController extends Controller
{
    public function __construct(private readonly RbacEnforcementGapAnalysisService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAbility($request, 'viewAny', RbacEnforcementGapAnalysis::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = RbacEnforcementGapAnalysis::query()
            ->with(['tenant', 'brand'])
            ->where('tenant_id', $user->tenant_id)
            ->when($request->query('status'), function ($builder, $status) {
                $builder->where('status', str($status)->lower()->slug('_')->value());
            })
            ->when($request->query('brand_id'), function ($builder, $brandId) use ($user) {
                if ($brandId === 'unscoped') {
                    $builder->whereNull('brand_id');

                    return;
                }

                if ($user->brand_id !== null && (int) $brandId !== (int) $user->brand_id) {
                    return;
                }

                $builder->where('brand_id', $brandId);
            })
            ->when($request->query('owner_team'), function ($builder, $ownerTeam) {
                $builder->where('owner_team', str($ownerTeam)->limit(120, '')->value());
            })
            ->when($request->query('reference_id'), function ($builder, $referenceId) {
                $builder->where('reference_id', str($referenceId)->limit(64, '')->value());
            })
            ->orderByDesc('analysis_date');

        if ($user->brand_id !== null) {
            $query->where(function ($builder) use ($user) {
                $builder->whereNull('brand_id')
                    ->orWhere('brand_id', $user->brand_id);
            });
        } elseif (! $user->hasRole('Admin')) {
            $query->whereNull('brand_id');
        }

        return RbacEnforcementGapAnalysisResource::collection($query->paginate());
    }

    public function store(StoreRbacEnforcementGapAnalysisRequest $request): JsonResponse
    {
        $this->authorizeAbility($request, 'create', RbacEnforcementGapAnalysis::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $analysis = $this->service->create($request->validated(), $user, $correlation);

        return (new RbacEnforcementGapAnalysisResource($analysis->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('X-Correlation-ID', $correlation);
    }

    public function show(RbacEnforcementGapAnalysis $rbacGapAnalysis): RbacEnforcementGapAnalysisResource
    {
        $this->authorizeAbility(request(), 'view', $rbacGapAnalysis);

        $rbacGapAnalysis->load(['tenant', 'brand']);

        return new RbacEnforcementGapAnalysisResource($rbacGapAnalysis);
    }

    public function update(UpdateRbacEnforcementGapAnalysisRequest $request, RbacEnforcementGapAnalysis $rbacGapAnalysis): JsonResponse
    {
        $this->authorizeAbility($request, 'update', $rbacGapAnalysis);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $analysis = $this->service->update($rbacGapAnalysis, $request->validated(), $user, $correlation);

        return (new RbacEnforcementGapAnalysisResource($analysis->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_OK)
            ->header('X-Correlation-ID', $correlation);
    }

    public function destroy(Request $request, RbacEnforcementGapAnalysis $rbacGapAnalysis): JsonResponse
    {
        $this->authorizeAbility($request, 'delete', $rbacGapAnalysis);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $this->service->delete($rbacGapAnalysis, $user, $correlation);

        return response()->json(null, Response::HTTP_NO_CONTENT)->header('X-Correlation-ID', $correlation);
    }

    protected function authorizeAbility(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], Response::HTTP_UNAUTHORIZED));
        }

        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $response->message() ?: 'This action is unauthorized.',
                ],
            ], Response::HTTP_FORBIDDEN));
        }
    }

    protected function correlationId(Request $request): string
    {
        $value = $request->header('X-Correlation-ID') ?? (string) str()->uuid();

        return str($value)->limit(64, '')->value();
    }
}
