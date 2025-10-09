<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $company = Company::query()->find($this->attributes['company_id'] ?? null);

        if (! $company) {
            $company = Company::factory()->create([
                'tenant_id' => $this->attributes['tenant_id'] ?? null,
                'brand_id' => $this->attributes['brand_id'] ?? null,
            ]);
        }

        $tenantId = $this->attributes['tenant_id'] ?? $company->tenant_id ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? $company->brand_id ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'company_id' => $company->id,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'metadata' => [],
            'tags' => $this->faker->randomElements(['vip', 'expansion', 'beta', 'gdpr'], 2),
            'gdpr_marketing_opt_in' => true,
            'gdpr_data_processing_opt_in' => true,
        ];
    }
}
