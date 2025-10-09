<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ObservabilityPipeline;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\ObservabilityPipeline>
 */
class ObservabilityPipelineFactory extends Factory
{
    protected $model = ObservabilityPipeline::class;

    public function definition(): array
    {
        $name = 'Pipeline '.Str::upper(Str::random(6));
        $type = $this->faker->randomElement(['logs', 'metrics']);

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numberBetween(100, 999)),
            'pipeline_type' => $type,
            'ingest_endpoint' => 'https://'.$this->faker->domainName().'/collect',
            'ingest_protocol' => 'https',
            'buffer_strategy' => Arr::random(['disk', 's3', 'memory']),
            'buffer_retention_seconds' => $this->faker->numberBetween(300, 3600),
            'retry_backoff_seconds' => $this->faker->numberBetween(5, 120),
            'max_retry_attempts' => $this->faker->numberBetween(1, 10),
            'batch_max_bytes' => $this->faker->numberBetween(512000, 4194304),
            'metrics_scrape_interval_seconds' => $type === 'metrics'
                ? $this->faker->numberBetween(15, 120)
                : null,
            'metadata' => [
                'description' => 'NON-PRODUCTION seed pipeline for tests.',
            ],
        ];
    }

    public function forBrand(?Brand $brand = null): self
    {
        return $this->state(function () use ($brand) {
            $resolved = $brand ?? Brand::factory()->create();

            return [
                'tenant_id' => $resolved->tenant_id,
                'brand_id' => $resolved->getKey(),
            ];
        });
    }
}
