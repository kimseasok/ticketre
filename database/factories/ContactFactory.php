<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'metadata' => [],
            'gdpr_consent' => true,
            'gdpr_consented_at' => now(),
            'gdpr_consent_method' => 'factory-import',
            'gdpr_consent_source' => 'NON_PRODUCTION',
            'gdpr_notes' => 'Seeded via factory '.Str::random(8),
        ];
    }

}
