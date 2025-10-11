<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortalSessionIndexRequest;
use App\Http\Resources\PortalSessionResource;
use App\Models\PortalSession;
use App\Services\PortalSessionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortalSessionController extends Controller
{
    public function __construct(private readonly PortalSessionService $sessions)
    {
    }

    public function index(PortalSessionIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PortalSession::class);

        $query = PortalSession::query()->with('account');
        $validated = $request->validated();

        if (isset($validated['portal_account_id'])) {
            $query->where('portal_account_id', $validated['portal_account_id']);
        }

        if (isset($validated['status'])) {
            if ($validated['status'] === 'active') {
                $query->whereNull('revoked_at')->where(function ($builder): void {
                    $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            } else {
                $query->whereNotNull('revoked_at');
            }
        }

        $user = $request->user();

        if (isset($validated['brand_id'])) {
            $query->where('brand_id', $validated['brand_id']);
        }

        if ($user && $user->brand_id) {
            $query->where(function ($builder) use ($user): void {
                $builder->whereNull('brand_id')->orWhere('brand_id', $user->brand_id);
            });
        }

        if (isset($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('correlation_id', 'like', $search)
                    ->orWhereHas('account', fn ($relation) => $relation->where('email', 'like', $search));
            });
        }

        $sessions = $query->orderByDesc('issued_at')->paginate(25);

        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        $resource = PortalSessionResource::collection($sessions)->additional([
            'meta' => ['correlation_id' => $correlationId],
        ]);

        $response = $resource->response();
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    public function show(Request $request, PortalSession $portalSession): JsonResponse
    {
        $this->enforceTenantContext($portalSession);
        $this->authorize('view', $portalSession);

        $portalSession->load('account');
        $correlationId = $this->resolveCorrelation($request->header('X-Correlation-ID'));

        $resource = PortalSessionResource::make($portalSession)->additional([
            'meta' => ['correlation_id' => $correlationId],
        ]);

        $response = $resource->response();
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    public function destroy(Request $request, PortalSession $portalSession): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthenticationException('Authentication required.');
        }

        $this->enforceTenantContext($portalSession);
        $this->authorize('delete', $portalSession);

        $correlationId = $this->sessions->revoke(
            $portalSession,
            $user,
            $request->header('X-Correlation-ID')
        );

        return response()->json(null, 204)->header('X-Correlation-ID', $correlationId);
    }

    protected function resolveCorrelation(?string $value): string
    {
        $candidate = $value ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    protected function enforceTenantContext(PortalSession $portalSession): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($tenant && (int) $portalSession->tenant_id !== (int) $tenant->getKey()) {
            abort(404);
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($brand && $portalSession->brand_id && (int) $portalSession->brand_id !== (int) $brand->getKey()) {
            abort(404);
        }
    }
}
