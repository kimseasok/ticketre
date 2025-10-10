<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandAssetRequest;
use App\Http\Requests\UpdateBrandAssetRequest;
use App\Http\Resources\BrandAssetResource;
use App\Models\BrandAsset;
use App\Services\BrandAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BrandAssetController extends Controller
{
    public function __construct(private readonly BrandAssetService $assetService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BrandAsset::class);

        $assets = BrandAsset::query()
            ->with(['brand', 'tenant'])
            ->when($request->query('brand_id'), function ($query, $brandId): void {
                $query->where('brand_id', $brandId);
            })
            ->when($request->query('type'), function ($query, $type): void {
                $query->where('type', $type);
            })
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('type', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate();

        return BrandAssetResource::collection($assets);
    }

    public function store(StoreBrandAssetRequest $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $asset = $this->assetService->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return (new BrandAssetResource($asset->load(['brand'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BrandAsset $brandAsset): BrandAssetResource
    {
        $this->authorize('view', $brandAsset);

        return new BrandAssetResource($brandAsset->load(['brand']));
    }

    public function update(UpdateBrandAssetRequest $request, BrandAsset $brandAsset): BrandAssetResource
    {
        $this->authorize('update', $brandAsset);
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $updated = $this->assetService->update($brandAsset, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return new BrandAssetResource($updated->load(['brand']));
    }

    public function destroy(Request $request, BrandAsset $brandAsset): JsonResponse
    {
        $this->authorize('delete', $brandAsset);
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $this->assetService->delete($brandAsset, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function deliver(Request $request, BrandAsset $brandAsset): JsonResponse
    {
        $this->authorize('view', $brandAsset);
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $payload = $this->assetService->deliver($brandAsset, $user, $request->header('X-Correlation-ID'));

        return response()->json([
            'data' => $payload,
        ], Response::HTTP_OK, [
            'Cache-Control' => $payload['cache_control'],
            'ETag' => $payload['etag'],
            'X-Brand-Asset-Version' => (string) $payload['version'],
        ]);
    }
}
