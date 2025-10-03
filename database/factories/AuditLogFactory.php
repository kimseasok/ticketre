<?php

namespace Database\Factories;

use App\Models\AuditLog;
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

        return [
            'tenant_id' => $user->tenant_id,
            'user_id' => $userId,
            'action' => 'created',
            'auditable_type' => Ticket::class,
            'auditable_id' => $this->attributes['auditable_id'] ?? 1,
            'changes' => ['before' => [], 'after' => []],
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
