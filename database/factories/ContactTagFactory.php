<?php

namespace Database\Factories;

use App\Models\ContactTag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContactTagFactory extends Factory
{
    protected $model = ContactTag::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id' => $tenantId,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
