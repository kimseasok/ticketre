<?php

namespace Database\Factories;

use App\Models\Brand;
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
        $name = $this->faker->unique()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numberBetween(100, 999)),
            'default_queue' => $this->faker->randomElement(['inbox', 'vip', 'backlog']),
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    public function forBrand(?Brand $brand = null): self
    {
        return $this->state(function () use ($brand) {
            $resolvedBrand = $brand ?? Brand::factory()->create();

            return [
                'tenant_id' => $resolvedBrand->tenant_id,
                'brand_id' => $resolvedBrand->getKey(),
            ];
        });
    }
}
