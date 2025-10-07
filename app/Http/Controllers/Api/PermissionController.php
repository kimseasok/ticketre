<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $service)
    {
        $this->middleware(EnsurePermission::class.':permissions.view')->only(['index', 'show']);
        $this->middleware(EnsurePermission::class.':permissions.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Permission::class);

        $query = Permission::query();

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        if ($request->boolean('system_only')) {
            $query->where('is_system', true);
        }

        $permissions = $query->orderBy('name')->get();

        return PermissionResource::collection($permissions);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->service->create($request->validated(), $request->user());

        return PermissionResource::make($permission)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Permission $permission): PermissionResource
    {
        $this->authorizeRequest($request, 'view', $permission);

        return PermissionResource::make($permission);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        $this->authorizeRequest($request, 'update', $permission);

        try {
            $permission = $this->service->update($permission, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_IMMUTABLE_PERMISSION',
                    'message' => $exception->getMessage(),
                ],
            ], 422));
        }

        return PermissionResource::make($permission);
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $permission);

        try {
            $this->service->delete($permission, $request->user());
        } catch (RuntimeException $exception) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_IMMUTABLE_PERMISSION',
                    'message' => $exception->getMessage(),
                ],
            ], 422));
        }

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
