<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $tenant = Tenant::query()->findOrFail($tenantId);

        $brandId = $this->attributes['brand_id'] ?? null;

        if ($brandId === null && $this->faker->boolean(40)) {
            $brandId = Brand::factory()->create(['tenant_id' => $tenant->id])->id;
        }

        $name = $this->faker->words(3, true).' SLA';
        $slug = Str::slug($name.'-'.$tenant->id.'-'.$this->faker->unique()->word());

        return [
            'tenant_id' => $tenant->id,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $slug,
            'timezone' => $this->faker->timezone(),
            'business_hours' => [
                ['day' => 'monday', 'start' => '09:00', 'end' => '17:00'],
                ['day' => 'tuesday', 'start' => '09:00', 'end' => '17:00'],
                ['day' => 'wednesday', 'start' => '09:00', 'end' => '17:00'],
                ['day' => 'thursday', 'start' => '09:00', 'end' => '17:00'],
                ['day' => 'friday', 'start' => '09:00', 'end' => '17:00'],
            ],
            'holiday_exceptions' => [
                ['date' => $this->faker->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'), 'name' => 'Seasonal Break'],
            ],
            'default_first_response_minutes' => $this->faker->numberBetween(15, 120),
            'default_resolution_minutes' => $this->faker->numberBetween(240, 1440),
            'enforce_business_hours' => true,
        ];
    }
}
