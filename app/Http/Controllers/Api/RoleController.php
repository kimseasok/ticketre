<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Role::class);

        $query = Role::query()->with('permissions');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like));
        }

        $roles = $query->orderBy('name')->get();

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->create($request->validated(), $request->user());

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Role $role): RoleResource
    {
        $this->authorizeRequest($request, 'view', $role);

        return RoleResource::make($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role = $this->service->update($role, $request->validated(), $request->user());

        return RoleResource::make($role);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $role);

        $this->service->delete($role->load('permissions'), $request->user());

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
