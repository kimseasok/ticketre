<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource as BrandApiResource;
use App\Models\Brand;
use App\Services\BrandConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    public function __construct(private readonly BrandConfigurationService $brandService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Brand::class);

        $brands = Brand::query()
            ->with(['domains', 'tenant'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate();

        return BrandApiResource::collection($brands);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $brand = $this->brandService->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return (new BrandApiResource($brand->load(['domains', 'tenant'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Brand $brand): BrandApiResource
    {
        $this->authorize('view', $brand);

        return new BrandApiResource($brand->load(['domains', 'tenant']));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandApiResource
    {
        $this->authorize('update', $brand);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->brandService->update($brand, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return new BrandApiResource($updated->load(['domains', 'tenant']));
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->brandService->delete($brand, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
