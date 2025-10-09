<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Validation\Rule;

class StoreTeamMembershipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        /** @var Team|null $team */
        $team = $this->route('team');

        if (! $team instanceof Team) {
            return false;
        }

        return $user->can('teams.manage') && $team->tenant_id === $user->tenant_id;
    }

    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $team->tenant_id),
                Rule::unique('team_memberships', 'user_id')->where(fn ($query) => $query
                    ->where('team_id', $team->getKey())
                    ->whereNull('deleted_at')),
            ],
            'role' => ['required', 'string', Rule::in(TeamMembership::ROLES)],
            'is_primary' => ['sometimes', 'boolean'],
            'joined_at' => ['sometimes', 'date'],
        ];
    }
}
