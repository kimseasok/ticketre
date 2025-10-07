<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
{
    protected $model = TeamMembership::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['lead', 'member', 'specialist']),
            'is_primary' => $this->faker->boolean(20),
        ];
    }

    public function forTeam(Team $team): self
    {
        return $this->state(function () use ($team): array {
            return [
                'team_id' => $team->getKey(),
                'tenant_id' => $team->tenant_id,
            ];
        });
    }

    public function forUser(User $user): self
    {
        return $this->state(function () use ($user): array {
            return [
                'user_id' => $user->getKey(),
                'tenant_id' => $user->tenant_id,
            ];
        });
    }
}
