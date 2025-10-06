<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = $this->attributes['tenant_id'] ?? Tenant::factory()->create();

        return [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
            'name' => Str::headline($this->faker->unique()->words(2, true)),
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'description' => $this->faker->sentence(),
            'guard_name' => 'web',
            'is_system' => false,
        ];
    }
}
