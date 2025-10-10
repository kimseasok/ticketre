<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlaPolicyRequest;
use App\Http\Requests\UpdateSlaPolicyRequest;
use App\Http\Resources\SlaPolicyResource;
use App\Models\SlaPolicy;
use App\Services\SlaPolicyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SlaPolicyController extends Controller
{
    public function __construct(private readonly SlaPolicyService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', SlaPolicy::class);

        $user = $request->user();
        $query = SlaPolicy::query()->with('targets');

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('name', 'like', $like)->orWhere('slug', 'like', $like);
            });
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        } elseif ($brandSlug = $request->query('brand_slug')) {
            $query->whereHas('brand', fn ($builder) => $builder->where('slug', $brandSlug));
        } else {
            $activeBrandId = null;

            if (app()->bound('currentBrand') && app('currentBrand')) {
                $activeBrandId = (int) app('currentBrand')->getKey();
            } elseif ($user && $user->brand_id) {
                $activeBrandId = (int) $user->brand_id;
            }

            if ($activeBrandId !== null) {
                $query->where(function ($builder) use ($activeBrandId): void {
                    $builder->whereNull('brand_id')->orWhere('brand_id', $activeBrandId);
                });
            } else {
                $query->whereNull('brand_id');
            }
        }

        $policies = $query->orderBy('name')->get();

        return SlaPolicyResource::collection($policies);
    }

    public function store(StoreSlaPolicyRequest $request): JsonResponse
    {
        $policy = $this->service->create($request->validated(), $request->user());

        return SlaPolicyResource::make($policy->load('targets'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SlaPolicy $slaPolicy): SlaPolicyResource
    {
        $this->authorizeRequest($request, 'view', $slaPolicy);

        return SlaPolicyResource::make($slaPolicy->load('targets'));
    }

    public function update(UpdateSlaPolicyRequest $request, SlaPolicy $slaPolicy): SlaPolicyResource
    {
        $policy = $this->service->update($slaPolicy, $request->validated(), $request->user());

        return SlaPolicyResource::make($policy);
    }

    public function destroy(Request $request, SlaPolicy $slaPolicy): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $slaPolicy);

        $this->service->delete($slaPolicy, $request->user());

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
