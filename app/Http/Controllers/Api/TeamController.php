<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $service)
    {
        $this->middleware(EnsurePermission::class.':teams.view')->only(['index', 'show']);
        $this->middleware(EnsurePermission::class.':teams.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Team::class);

        $query = Team::query()->with(['memberships.user'])->withCount('memberships');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like));
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        $teams = $query->orderBy('name')->get();

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->service->create($request->validated(), $request->user());

        return TeamResource::make($team)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Team $team): TeamResource
    {
        $this->authorizeRequest($request, 'view', $team);

        return TeamResource::make($team->load(['memberships.user']));
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team = $this->service->update($team, $request->validated(), $request->user());

        return TeamResource::make($team);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $team);

        $this->service->delete($team, $request->user());

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
