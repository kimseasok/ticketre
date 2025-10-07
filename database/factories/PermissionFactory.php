<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ucfirst($name),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->optional()->sentence(8),
            'guard_name' => 'web',
            'is_system' => false,
        ];
    }

    public function system(): self
    {
        return $this->state(fn () => [
            'tenant_id' => null,
            'is_system' => true,
        ]);
    }
}
