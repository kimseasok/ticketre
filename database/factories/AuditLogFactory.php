<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $userId = $this->attributes['user_id'] ?? User::factory()->create()->id;
        $user = User::find($userId);

        $tenantId = $this->attributes['tenant_id']
            ?? $user?->tenant_id
            ?? Tenant::factory()->create()->id;

        $brandId = $this->attributes['brand_id']
            ?? $user?->brand_id
            ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'user_id' => $userId,
            'action' => 'created',
            'auditable_type' => Ticket::class,
            'auditable_id' => $this->attributes['auditable_id'] ?? 1,
            'changes' => ['before' => [], 'after' => []],
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
