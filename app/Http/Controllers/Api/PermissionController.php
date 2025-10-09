<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource as PermissionApiResource;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Permission::class);

        $query = Permission::query()->with('brand');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like));
        }

        if ($brand = $request->query('brand')) {
            if ($brand === 'global') {
                $query->whereNull('brand_id');
            } elseif (is_numeric($brand)) {
                $query->where('brand_id', (int) $brand);
            }
        }

        $permissions = $query->orderBy('name')->get();

        return PermissionApiResource::collection($permissions);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->service->create($request->validated(), $request->user());

        return PermissionApiResource::make($permission)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Permission $permission): PermissionApiResource
    {
        $this->authorizeRequest($request, 'view', $permission);

        return PermissionApiResource::make($permission->load('brand'));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionApiResource
    {
        $permission = $this->service->update($permission, $request->validated(), $request->user());

        return PermissionApiResource::make($permission->loadMissing('brand'));
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $permission);

        $this->service->delete($permission->load('brand'), $request->user());

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
