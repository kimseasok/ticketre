<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\PermissionCoverageReport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionCoverageReport>
 */
class PermissionCoverageReportFactory extends Factory
{
    protected $model = PermissionCoverageReport::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(5, 60);
        $unguarded = $this->faker->numberBetween(0, (int) floor($total / 5));
        $guarded = max(0, $total - $unguarded);
        $coverage = $total === 0 ? 100.0 : round(($guarded / $total) * 100, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => Brand::factory(),
            'module' => $this->faker->randomElement(PermissionCoverageReport::MODULES),
            'total_routes' => $total,
            'guarded_routes' => $guarded,
            'unguarded_routes' => $unguarded,
            'coverage' => $coverage,
            'unguarded_paths' => $unguarded === 0 ? [] : [$this->faker->unique()->slug()],
            'metadata' => [
                'build_id' => $this->faker->uuid(),
                'initiated_by' => 'NON-PRODUCTION seeder',
            ],
            'notes' => $this->faker->sentence(),
            'generated_at' => now()->subMinutes($this->faker->numberBetween(1, 120)),
        };
    }

    public function unscopedBrand(): self
    {
        return $this->state(function () {
            return [
                'brand_id' => null,
            ];
        });
    }
}
