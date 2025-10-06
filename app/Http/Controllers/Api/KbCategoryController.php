<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKbCategoryRequest;
use App\Http\Requests\UpdateKbCategoryRequest;
use App\Http\Resources\KbCategoryResource;
use App\Models\KbCategory;
use App\Models\User;
use App\Services\KbCategoryService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KbCategoryController extends Controller
{
    public function __construct(private readonly KbCategoryService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'viewAny', KbCategory::class);

        $categoriesQuery = KbCategory::query()
            ->with('parent')
            ->where('tenant_id', $user->tenant_id);

        if ($user->brand_id !== null) {
            $categoriesQuery->where('brand_id', $user->brand_id);
        }

        $categories = $categoriesQuery
            ->orderBy('path')
            ->get();

        return KbCategoryResource::collection($categories);
    }

    public function store(StoreKbCategoryRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'create', KbCategory::class);

        $category = $this->service->create($request->validated(), $user);

        return KbCategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, KbCategory $kbCategory): KbCategoryResource
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'view', $kbCategory);

        return KbCategoryResource::make($kbCategory->load('parent'));
    }

    public function update(UpdateKbCategoryRequest $request, KbCategory $kbCategory): KbCategoryResource
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'update', $kbCategory);

        $category = $this->service->update($kbCategory, $request->validated(), $user);

        return KbCategoryResource::make($category);
    }

    public function destroy(Request $request, KbCategory $kbCategory): JsonResponse
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'delete', $kbCategory);

        $this->service->delete($kbCategory, $user);

        return response()->json(null, 204);
    }

    protected function authorizeForRequest(Request $request, User $user, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            $this->throwAuthorizationError($response->message() ?: 'This action is unauthorized.');
        }
    }

    protected function throwAuthorizationError(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => $message,
            ],
        ], 403));
    }

    protected function resolveUser(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ], 401));
    }
}
