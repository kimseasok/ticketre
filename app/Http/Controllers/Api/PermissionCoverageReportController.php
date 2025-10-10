<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionCoverageReportRequest;
use App\Http\Requests\UpdatePermissionCoverageReportRequest;
use App\Http\Resources\PermissionCoverageReportResource;
use App\Models\PermissionCoverageReport;
use App\Services\PermissionCoverageReportService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PermissionCoverageReportController extends Controller
{
    public function __construct(private readonly PermissionCoverageReportService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAbility($request, 'viewAny', PermissionCoverageReport::class);

        $query = PermissionCoverageReport::query()
            ->with(['tenant', 'brand'])
            ->when($request->query('module'), function ($builder, $module) {
                $builder->where('module', str($module)->lower()->slug('_')->value());
            })
            ->when($request->query('brand_id'), function ($builder, $brandId) {
                if ($brandId === 'unscoped') {
                    $builder->whereNull('brand_id');
                } else {
                    $builder->where('brand_id', $brandId);
                }
            })
            ->orderByDesc('generated_at');

        return PermissionCoverageReportResource::collection($query->paginate());
    }

    public function store(StorePermissionCoverageReportRequest $request): JsonResponse
    {
        $this->authorizeAbility($request, 'create', PermissionCoverageReport::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $report = $this->service->create($request->validated(), $user, $correlation);

        return (new PermissionCoverageReportResource($report->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('X-Correlation-ID', $correlation);
    }

    public function show(PermissionCoverageReport $permissionCoverageReport): PermissionCoverageReportResource
    {
        $this->authorizeAbility(request(), 'view', $permissionCoverageReport);

        $permissionCoverageReport->load(['tenant', 'brand']);

        return new PermissionCoverageReportResource($permissionCoverageReport);
    }

    public function update(UpdatePermissionCoverageReportRequest $request, PermissionCoverageReport $permissionCoverageReport): JsonResponse
    {
        $this->authorizeAbility($request, 'update', $permissionCoverageReport);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);

        $report = $this->service->update($permissionCoverageReport, $request->validated(), $user, $correlation);

        return (new PermissionCoverageReportResource($report->loadMissing(['tenant', 'brand'])))
            ->additional(['meta' => ['correlation_id' => $correlation]])
            ->response()
            ->setStatusCode(Response::HTTP_OK)
            ->header('X-Correlation-ID', $correlation);
    }

    public function destroy(Request $request, PermissionCoverageReport $permissionCoverageReport): JsonResponse
    {
        $this->authorizeAbility($request, 'delete', $permissionCoverageReport);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlation = $this->correlationId($request);
        $this->service->delete($permissionCoverageReport, $user, $correlation);

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

        return str($value)->limit(64, '');
    }
}
