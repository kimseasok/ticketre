<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $tenant = Tenant::query()->findOrFail($tenantId);
        $name = $this->faker->company().' Brand';
        $slug = Str::slug($name.'-'.$tenant->id);

        return [
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'domain' => $slug.'.'.$tenant->domain,
            'theme' => [
                'primary' => '#2563eb',
            ],
        ];
    }
}
