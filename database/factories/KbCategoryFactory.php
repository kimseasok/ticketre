<?php

namespace Database\Factories;

use App\Models\KbCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $name = $this->faker->words(3, true);

        return [
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$tenantId),
            'order' => $this->faker->numberBetween(1, 10),
            'parent_id' => $this->attributes['parent_id'] ?? null,
        ];
    }
}
