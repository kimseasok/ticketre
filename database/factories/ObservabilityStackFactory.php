<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ObservabilityStack;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\ObservabilityStack>
 */
class ObservabilityStackFactory extends Factory
{
    protected $model = ObservabilityStack::class;

    public function definition(): array
    {
        $name = 'Stack '.Str::upper(Str::random(5));

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numberBetween(100, 999)),
            'status' => $this->faker->randomElement(['evaluating', 'selected', 'deprecated']),
            'logs_tool' => Arr::random(['elk', 'opensearch', 'loki']),
            'metrics_tool' => Arr::random(['prometheus', 'grafana-metrics', 'cloudwatch']),
            'alerts_tool' => Arr::random(['grafana-alerting', 'pagerduty', 'opsgenie']),
            'log_retention_days' => $this->faker->numberBetween(7, 90),
            'metric_retention_days' => $this->faker->numberBetween(7, 90),
            'trace_retention_days' => $this->faker->optional()->numberBetween(1, 30),
            'estimated_monthly_cost' => $this->faker->randomFloat(2, 100, 2500),
            'trace_sampling_strategy' => Arr::random(['head', 'tail', 'probabilistic 10%', 'adaptive']),
            'decision_matrix' => [
                'elk' => [
                    'cost' => 850.00,
                    'scalability' => 'Requires dedicated cluster management.',
                ],
                'loki' => [
                    'cost' => 420.00,
                    'scalability' => 'Horizontally scalable with object storage.',
                ],
            ],
            'security_notes' => 'NON-PRODUCTION seed demonstrating SOC2-aligned controls.',
            'compliance_notes' => 'GDPR-compliant retention enforced through automation.',
            'metadata' => [
                'owner' => 'platform-observability',
                'environment' => 'non-production',
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
