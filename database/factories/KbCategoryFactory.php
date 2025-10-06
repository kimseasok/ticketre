<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\KbCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    public function definition(): array
    {
        $tenant = isset($this->attributes['tenant_id'])
            ? Tenant::query()->findOrFail($this->attributes['tenant_id'])
            : Tenant::factory()->create();

        $brand = isset($this->attributes['brand_id'])
            ? Brand::query()->findOrFail($this->attributes['brand_id'])
            : Brand::factory()->create(['tenant_id' => $tenant->id]);

        $name = $this->faker->words(3, true);

        return [
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$brand->id),
            'order' => $this->faker->numberBetween(1, 10),
            'parent_id' => $this->attributes['parent_id'] ?? null,
        ];
    }
}
