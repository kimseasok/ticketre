<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\HorizonDeployment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HorizonDeployment>
 */
class HorizonDeploymentFactory extends Factory
{
    protected $model = HorizonDeployment::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Horizon';
        $slug = Str::slug($name.'-'.$this->faker->lexify('????'));

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => $slug,
            'domain' => $this->faker->unique()->domainName(),
            'auth_guard' => 'web',
            'horizon_connection' => 'sync',
            'uses_tls' => true,
            'supervisors' => [
                [
                    'name' => 'app-supervisor',
                    'connection' => 'sync',
                    'queue' => ['default'],
                    'balance' => 'simple',
                    'min_processes' => 1,
                    'max_processes' => 3,
                    'max_jobs' => 0,
                    'max_time' => 0,
                    'timeout' => 60,
                    'tries' => 1,
                ],
            ],
            'last_deployed_at' => now(),
            'last_health_status' => 'unknown',
            'metadata' => [
                'notes' => 'NON-PRODUCTION factory seed for tests.',
            ],
        ];
    }

    public function forBrand(?Brand $brand = null): self
    {
        return $this->state(function () use ($brand) {
            return [
                'brand_id' => $brand?->getKey() ?? Brand::factory(),
            ];
        });
    }
}
