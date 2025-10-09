<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamMembershipRequest;
use App\Http\Requests\UpdateTeamMembershipRequest;
use App\Http\Resources\TeamMembershipResource;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Services\TeamMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TeamMembershipController extends Controller
{
    public function __construct(private readonly TeamMembershipService $membershipService)
    {
    }

    public function index(Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        $memberships = $team->memberships()->with('user')->paginate();

        return TeamMembershipResource::collection($memberships);
    }

    public function store(StoreTeamMembershipRequest $request, Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $membership = $this->membershipService->attach($team, $request->validated(), $user);

        return (new TeamMembershipResource($membership))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function show(Team $team, TeamMembership $teamMembership): TeamMembershipResource
    {
        $this->authorize('view', $team);

        if ($teamMembership->team_id !== $team->getKey()) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $teamMembership);

        $teamMembership->load('user');

        return new TeamMembershipResource($teamMembership);
    }

    public function update(UpdateTeamMembershipRequest $request, Team $team, TeamMembership $teamMembership): TeamMembershipResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($teamMembership->team_id !== $team->getKey()) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $teamMembership);

        $updated = $this->membershipService->update($teamMembership, $request->validated(), $user);

        return new TeamMembershipResource($updated);
    }

    public function destroy(Team $team, TeamMembership $teamMembership): JsonResponse
    {
        $this->authorize('delete', $teamMembership);

        if ($teamMembership->team_id !== $team->getKey()) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        /** @var \App\Models\User $user */
        $user = request()->user();

        $this->membershipService->detach($teamMembership, $user);

        return response()->json(null, SymfonyResponse::HTTP_NO_CONTENT);
    }
}
