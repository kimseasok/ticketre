<?php

namespace Database\Factories;

use App\Models\ContactTag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactTag>
 */
class ContactTagFactory extends Factory
{
    protected $model = ContactTag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'color' => $this->faker->safeHexColor(),
            'metadata' => [
                'non_production' => true,
            ],
        ];
    }
}
