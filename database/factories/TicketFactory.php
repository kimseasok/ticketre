<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketDepartment;
use App\Models\TicketTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $tenant = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brand = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenant])->id;
        $company = $this->attributes['company_id'] ?? Company::factory()->create(['tenant_id' => $tenant])->id;
        $contact = $this->attributes['contact_id'] ?? Contact::factory()->create([
            'tenant_id' => $tenant,
            'company_id' => $company,
        ])->id;
        $assignee = $this->attributes['assignee_id'] ?? User::factory()->create([
            'tenant_id' => $tenant,
            'brand_id' => $brand,
        ])->id;
        $departmentId = $this->attributes['department_id'] ?? TicketDepartment::factory()->create([
            'tenant_id' => $tenant,
            'brand_id' => $brand,
        ])->id;
        $department = TicketDepartment::withoutGlobalScopes()->find($departmentId);

        return [
            'tenant_id' => $tenant,
            'brand_id' => $brand,
            'company_id' => $company,
            'contact_id' => $contact,
            'assignee_id' => $assignee,
            'department_id' => $departmentId,
            'subject' => $this->faker->sentence(),
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'channel' => Ticket::CHANNEL_AGENT,
            'department' => $department?->name,
            'category' => null,
            'workflow_state' => 'new',
            'metadata' => [],
            'sla_due_at' => now()->addHours(8),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Ticket $ticket): void {
            if ($ticket->categories()->count() === 0) {
                $category = TicketCategory::factory()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'brand_id' => $ticket->brand_id,
                ]);

                $ticket->categories()->sync([$category->getKey()]);
                $ticket->forceFill(['category' => $category->name])->save();
            }

            if ($ticket->tags()->count() === 0) {
                $tag = TicketTag::factory()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'brand_id' => $ticket->brand_id,
                ]);

                $ticket->tags()->sync([$tag->getKey()]);
            }
        });
    }
}
