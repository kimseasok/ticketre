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
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'metadata' => [],
            'gdpr_marketing_opt_in' => true,
            'gdpr_tracking_opt_in' => $this->faker->boolean(),
            'gdpr_consent_recorded_at' => now(),
        ];
    }
}
