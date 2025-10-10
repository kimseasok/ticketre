<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandDomainRequest;
use App\Http\Requests\UpdateBrandDomainRequest;
use App\Http\Requests\VerifyBrandDomainRequest;
use App\Http\Resources\BrandDomainResource;
use App\Models\Brand;
use App\Models\BrandDomain;
use App\Services\BrandDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BrandDomainController extends Controller
{
    public function __construct(private readonly BrandDomainService $brandDomainService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BrandDomain::class);

        $domains = BrandDomain::query()
            ->with(['brand'])
            ->when($request->query('brand_id'), fn ($query, $brandId) => $query->where('brand_id', $brandId))
            ->orderByDesc('updated_at')
            ->paginate();

        return BrandDomainResource::collection($domains);
    }

    public function store(StoreBrandDomainRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $brand = Brand::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($request->integer('brand_id'));

        $domain = $this->brandDomainService->create($request->validated(), $brand, $user, $request->header('X-Correlation-ID'));

        return (new BrandDomainResource($domain->load('brand')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BrandDomain $brandDomain): BrandDomainResource
    {
        $this->authorize('view', $brandDomain);

        return new BrandDomainResource($brandDomain->load('brand'));
    }

    public function update(UpdateBrandDomainRequest $request, BrandDomain $brandDomain): BrandDomainResource
    {
        $this->authorize('update', $brandDomain);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $updated = $this->brandDomainService->update($brandDomain, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return new BrandDomainResource($updated->load('brand'));
    }

    public function destroy(Request $request, BrandDomain $brandDomain): JsonResponse
    {
        $this->authorize('delete', $brandDomain);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->brandDomainService->delete($brandDomain, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function verify(VerifyBrandDomainRequest $request, BrandDomain $brandDomain): JsonResponse
    {
        $this->authorize('verify', $brandDomain);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->brandDomainService->beginVerification($brandDomain, $user, $request->input('correlation_id') ?? $request->header('X-Correlation-ID'));

        return response()->json([
            'data' => [
                'status' => 'queued',
                'domain' => $brandDomain->domain,
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
