<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\TicketDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketDepartmentFactory extends Factory
{
    protected $model = TicketDepartment::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numberBetween(1, 9999)),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
