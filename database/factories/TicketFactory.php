<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\Tenant;
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

        $workflow = null;

        if (isset($this->attributes['ticket_workflow_id'])) {
            $workflow = TicketWorkflow::find($this->attributes['ticket_workflow_id']);
        }

        if (! $workflow) {
            $workflow = TicketWorkflow::factory()->default()->create([
                'tenant_id' => $tenant,
                'brand_id' => $brand,
                'is_default' => true,
            ]);

            TicketWorkflowState::factory()->initial()->create([
                'tenant_id' => $tenant,
                'brand_id' => $brand,
                'ticket_workflow_id' => $workflow->getKey(),
                'slug' => 'new',
                'name' => 'New',
                'position' => 0,
            ]);
        }

        return [
            'tenant_id' => $tenant,
            'brand_id' => $brand,
            'ticket_workflow_id' => $workflow->getKey(),
            'company_id' => $company,
            'contact_id' => $contact,
            'assignee_id' => $assignee,
            'subject' => $this->faker->sentence(),
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'channel' => Ticket::CHANNEL_AGENT,
            'department' => 'support',
            'category' => 'general',
            'workflow_state' => $this->attributes['workflow_state'] ?? 'new',
            'metadata' => [],
            'custom_fields' => [
                [
                    'key' => 'source',
                    'type' => 'string',
                    'value' => 'factory',
                ],
            ],
            'sla_due_at' => now()->addHours(8),
        ];
    }
}
