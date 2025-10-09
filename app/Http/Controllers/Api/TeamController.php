<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teamService)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::query()
            ->with(['memberships.user'])
            ->withCount('memberships')
            ->orderByDesc('updated_at')
            ->paginate();

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $team = $this->teamService->create($request->validated(), $user);

        return (new TeamResource($team->loadMissing(['memberships.user'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        $team->load(['memberships.user'])->loadCount('memberships');

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->teamService->update($team, $request->validated(), $user);

        return new TeamResource($updated->loadMissing(['memberships.user'])->loadCount('memberships'));
    }

    public function destroy(Team $team, Request $request): JsonResponse
    {
        $this->authorize('delete', $team);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->teamService->delete($team, $user);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
