<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Team';

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.' '.$this->faker->unique()->randomNumber()),
            'default_queue' => $this->faker->randomElement(['general', 'vip', 'escalations']),
            'description' => $this->faker->sentence(),
        ];
    }
}
