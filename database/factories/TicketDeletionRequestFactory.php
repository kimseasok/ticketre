<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketDeletionRequest>
 */
class TicketDeletionRequestFactory extends Factory
{
    protected $model = TicketDeletionRequest::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
        ]);
        $requester = User::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_id' => $ticket->id,
            'requested_by' => $requester->id,
            'status' => TicketDeletionRequest::STATUS_PENDING,
            'reason' => fake()->sentence(),
            'correlation_id' => (string) Str::uuid(),
        ];
    }
}
