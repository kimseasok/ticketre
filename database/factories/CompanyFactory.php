<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'name' => $this->faker->company(),
            'domain' => $this->faker->domainName(),
            'metadata' => [],
            'tags' => $this->faker->randomElements(['vip', 'enterprise', 'onboarding', 'retail'], 2),
        ];
    }
}
