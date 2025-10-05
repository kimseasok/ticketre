<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $companyId = $this->attributes['company_id'] ?? Company::factory()->create()->id;
        $company = Company::query()->findOrFail($companyId);

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $companyId,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'metadata' => [],
        ];
    }
}
