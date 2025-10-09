<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateTeamMembershipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var TeamMembership|null $teamMembership */
        $teamMembership = $this->route('teamMembership');

        if (! $team instanceof Team || ! $teamMembership instanceof TeamMembership) {
            return false;
        }

        return $user->can('teams.manage') && $team->tenant_id === $user->tenant_id && $teamMembership->team_id === $team->getKey();
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'string', Rule::in(TeamMembership::ROLES)],
            'is_primary' => ['sometimes', 'boolean'],
            'joined_at' => ['sometimes', 'date'],
        ];
    }
}
