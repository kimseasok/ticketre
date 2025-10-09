<?php

namespace Database\Factories;

use App\Models\CiQualityGate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CiQualityGate>
 */
class CiQualityGateFactory extends Factory
{
    protected $model = CiQualityGate::class;

    public function definition(): array
    {
        $name = 'Quality Gate '.$this->faker->unique()->word();

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->numberBetween(100, 999)),
            'coverage_threshold' => $this->faker->numberBetween(70, 95),
            'max_critical_vulnerabilities' => 0,
            'max_high_vulnerabilities' => $this->faker->numberBetween(0, 1),
            'enforce_dependency_audit' => true,
            'enforce_docker_build' => true,
            'notifications_enabled' => true,
            'notify_channel' => '#ci-alerts',
            'metadata' => [
                'owner' => $this->faker->randomElement(['platform-team', 'qa-team']),
                'description' => 'NON-PRODUCTION default seed for pipelines.',
            ],
        ];
    }
}
