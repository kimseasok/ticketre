<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMerge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketMergeFactory extends Factory
{
    protected $model = TicketMerge::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;

        $primary = $this->attributes['primary_ticket_id'] ?? Ticket::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
        ])->id;

        $secondary = $this->attributes['secondary_ticket_id'] ?? Ticket::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
        ])->id;

        $initiator = $this->attributes['initiated_by'] ?? User::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
        ])->id;

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'primary_ticket_id' => $primary,
            'secondary_ticket_id' => $secondary,
            'initiated_by' => $initiator,
            'status' => TicketMerge::STATUS_COMPLETED,
            'summary' => [
                'messages_migrated' => 0,
                'events_migrated' => 0,
                'metadata_keys_merged' => [],
            ],
            'correlation_id' => $this->faker->uuid(),
            'completed_at' => now(),
        ];
    }
}
