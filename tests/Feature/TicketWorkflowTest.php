<?php

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TicketWorkflowTransition;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }
});

function workflowHeaders(Tenant $tenant, ?Brand $brand = null): array
{
    $headers = [
        'X-Tenant' => $tenant->slug,
        'Accept' => 'application/json',
    ];

    if ($brand) {
        $headers['X-Brand'] = $brand->slug;
    }

    return $headers;
}

it('E1-F4-I3 creates ticket workflows via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'Escalation Workflow',
        'slug' => 'escalation',
        'is_default' => true,
        'states' => [
            [
                'name' => 'Open',
                'slug' => 'open',
                'is_initial' => true,
                'position' => 0,
            ],
            [
                'name' => 'Escalated',
                'slug' => 'escalated',
                'position' => 1,
                'sla_minutes' => 180,
            ],
        ],
        'transitions' => [
            [
                'from' => 'open',
                'to' => 'escalated',
                'requires_comment' => true,
                'metadata' => ['channel' => 'NON-PRODUCTION'],
            ],
        ],
    ];

    $response = postJson('/api/v1/ticket-workflows', $payload, workflowHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.slug', 'escalation');
    $response->assertJsonPath('data.attributes.states.0.slug', 'open');
    $response->assertJsonPath('data.attributes.transitions.0.requires_comment', true);

    expect(TicketWorkflow::where('slug', 'escalation')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E1-F4-I3 rejects workflows without initial state', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'Invalid Workflow',
        'slug' => 'invalid-workflow',
        'states' => [
            ['name' => 'Stage One', 'slug' => 'stage-one'],
            ['name' => 'Stage Two', 'slug' => 'stage-two'],
        ],
    ];

    $response = postJson('/api/v1/ticket-workflows', $payload, workflowHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F4-I3 enforces workflow transition rules', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $workflow = TicketWorkflow::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'is_default' => true,
    ]);

    $new = TicketWorkflowState::factory()->initial()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'slug' => 'new',
        'name' => 'New',
        'position' => 0,
    ]);

    $review = TicketWorkflowState::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'slug' => 'review',
        'name' => 'Review',
        'position' => 1,
        'sla_minutes' => 120,
        'entry_hook' => null,
    ]);

    TicketWorkflowTransition::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'from_state_id' => $new->id,
        'to_state_id' => $review->id,
        'requires_comment' => true,
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'workflow_state' => 'new',
        'status' => 'open',
    ]);

    /** @var TicketService $service */
    $service = app(TicketService::class);

    $updated = $service->update($ticket, [
        'workflow_state' => 'review',
        'workflow_context' => ['comment' => 'NON-PRODUCTION transition'],
    ], $admin);

    expect($updated->workflow_state)->toBe('review');
    expect($updated->sla_due_at)->not()->toBeNull();

    expect(fn () => $service->update($updated, [
        'workflow_state' => 'closed',
    ], $admin))->toThrow(ValidationException::class);
});

it('E1-F4-I3 restricts workflow access by role', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($agent);
    $agentResponse = getJson('/api/v1/ticket-workflows', workflowHeaders($tenant, $brand));
    $agentResponse->assertOk();

    actingAs($viewer);
    $viewerResponse = getJson('/api/v1/ticket-workflows', workflowHeaders($tenant, $brand));
    $viewerResponse->assertForbidden();

    actingAs($admin);
    $createResponse = postJson('/api/v1/ticket-workflows', [
        'name' => 'Policy Workflow',
        'slug' => 'policy-workflow',
        'states' => [
            ['name' => 'Start', 'slug' => 'start', 'is_initial' => true],
            ['name' => 'End', 'slug' => 'end'],
        ],
    ], workflowHeaders($tenant, $brand));

    $createResponse->assertCreated();
});

it('E1-F4-I3 enforces tenant isolation for workflows', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    app()->instance('currentTenant', $tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);

    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    app()->instance('currentBrand', $brandA);

    $workflow = TicketWorkflow::factory()->default()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
        'is_default' => true,
    ]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);
    $tenantBAdmin = User::factory()->create(['tenant_id' => $tenantB->id]);
    $tenantBAdmin->assignRole('Admin');
    actingAs($tenantBAdmin);

    $response = getJson('/api/v1/ticket-workflows/'.$workflow->id, workflowHeaders($tenantB));
    $response->assertForbidden();
});

it('E1-F4-I3 returns standard error structure on transition failure', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $workflow = TicketWorkflow::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'is_default' => true,
    ]);

    $initial = TicketWorkflowState::factory()->initial()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'slug' => 'new',
    ]);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_workflow_id' => $workflow->id,
        'workflow_state' => 'new',
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    $response = patchJson('/api/v1/tickets/'.$ticket->id, [
        'workflow_state' => 'non-existent',
    ], workflowHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});
