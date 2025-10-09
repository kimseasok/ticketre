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
            'brand_id' => null,
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(TeamMembership::ROLES),
            'is_primary' => $this->faker->boolean(20),
            'joined_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TeamMembership $membership) {
            if ($membership->team) {
                $membership->tenant_id = $membership->team->tenant_id;
                $membership->brand_id = $membership->team->brand_id;
            }

            if ($membership->user && $membership->user->tenant_id === null) {
                $membership->user->tenant_id = $membership->tenant_id;
                $membership->user->brand_id = $membership->brand_id;
            }
        })->afterCreating(function (TeamMembership $membership) {
            $team = $membership->team()->withoutGlobalScopes()->first();

            if ($team) {
                $membership->tenant_id = $team->tenant_id;
                $membership->brand_id = $team->brand_id;
                $membership->save();
            }
        });
    }
}
