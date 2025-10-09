<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->getKey();
        $brandId = $this->attributes['brand_id'] ?? null;

        if ($brandId === null && $this->faker->boolean(30)) {
            $brandId = Brand::factory()->create(['tenant_id' => $tenantId])->getKey();
        }

        $name = Str::slug($this->faker->unique()->words(3, true), '.');

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $name,
            'description' => $this->faker->sentence(),
            'guard_name' => 'web',
            'is_system' => false,
        ];
    }
}
