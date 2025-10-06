<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKbCategoryRequest;
use App\Http\Requests\UpdateKbCategoryRequest;
use App\Http\Resources\KbCategoryResource;
use App\Models\KbCategory;
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
        $this->authorizeForRequest($request, 'viewAny', KbCategory::class);

        $categories = KbCategory::query()
            ->with('parent')
            ->orderBy('path')
            ->get();

        return KbCategoryResource::collection($categories);
    }

    public function store(StoreKbCategoryRequest $request): JsonResponse
    {
        $this->authorizeForRequest($request, 'create', KbCategory::class);

        $category = $this->service->create($request->validated(), $request->user());

        return KbCategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, KbCategory $kbCategory): KbCategoryResource
    {
        $this->authorizeForRequest($request, 'view', $kbCategory);

        return KbCategoryResource::make($kbCategory->load('parent'));
    }

    public function update(UpdateKbCategoryRequest $request, KbCategory $kbCategory): KbCategoryResource
    {
        $this->authorizeForRequest($request, 'update', $kbCategory);

        $category = $this->service->update($kbCategory, $request->validated(), $request->user());

        return KbCategoryResource::make($category);
    }

    public function destroy(Request $request, KbCategory $kbCategory): JsonResponse
    {
        $this->authorizeForRequest($request, 'delete', $kbCategory);

        $this->service->delete($kbCategory, $request->user());

        return response()->json(null, 204);
    }

    protected function authorizeForRequest(Request $request, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($request->user())->inspect($ability, $arguments);

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
}
