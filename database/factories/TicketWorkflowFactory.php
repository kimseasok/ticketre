<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\TicketWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketWorkflowFactory extends Factory
{
    protected $model = TicketWorkflow::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name.'-'.Str::random(5)),
            'description' => $this->faker->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): self
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function forBrand(?Brand $brand = null): self
    {
        return $this->state(function (array $attributes) use ($brand): array {
            $resolvedBrand = $brand ?? Brand::factory()->create([
                'tenant_id' => $attributes['tenant_id'] instanceof Tenant
                    ? $attributes['tenant_id']->getKey()
                    : $attributes['tenant_id'],
            ]);

            return [
                'brand_id' => $resolvedBrand->getKey(),
                'tenant_id' => $resolvedBrand->tenant_id,
            ];
        });
    }
}
