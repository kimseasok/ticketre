<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;

        return [
            'tenant_id' => $tenantId,
            'name' => $this->faker->company(),
            'domain' => $this->faker->domainName(),
            'metadata' => [],
        ];
    }
}
