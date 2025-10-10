<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function slaHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function confirmTwoFactor(User $user): void
{
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $user->tenant_id,
        'brand_id' => $user->brand_id,
        'user_id' => $user->getKey(),
    ]);

    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E4-F3-I2 allows admins to create SLA policies with targets', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);
    confirmTwoFactor($admin);

    $payload = [
        'name' => 'Premier Support SLA',
        'timezone' => 'Europe/Paris',
        'business_hours' => [
            ['day' => 'monday', 'start' => '09:00', 'end' => '17:00'],
            ['day' => 'tuesday', 'start' => '09:00', 'end' => '17:00'],
        ],
        'holiday_exceptions' => ['2025-12-25'],
        'default_first_response_minutes' => 60,
        'default_resolution_minutes' => 1440,
        'targets' => [
            [
                'channel' => 'email',
                'priority' => 'high',
                'first_response_minutes' => 30,
                'resolution_minutes' => 720,
                'use_business_hours' => true,
            ],
        ],
    ];

    $response = postJson('/api/v1/sla-policies', $payload, slaHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Premier Support SLA');
    $response->assertJsonPath('data.targets.0.channel', 'email');

    $policyId = $response->json('data.id');

    expect(SlaPolicy::query()->where('id', $policyId)->exists())->toBeTrue();

    $audit = AuditLog::query()
        ->where('auditable_type', SlaPolicy::class)
        ->where('auditable_id', $policyId)
        ->where('action', 'sla-policy.created')
        ->first();

    expect($audit)->not->toBeNull();

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 prevents agents from creating SLA policies', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);
    confirmTwoFactor($agent);

    $response = postJson('/api/v1/sla-policies', [
        'name' => 'Unauthorized Policy',
        'timezone' => 'UTC',
    ], slaHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 validates timezone input when creating policies', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);
    confirmTwoFactor($admin);

    $response = postJson('/api/v1/sla-policies', [
        'name' => 'Invalid Policy',
        'timezone' => 'Invalid/Timezone',
    ], slaHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 lists SLA policies scoped by tenant and filters by brand', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherBrand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->forgetInstance('currentBrand');

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    SlaPolicy::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => null,
        'name' => 'Tenant Default SLA',
        'slug' => 'tenant-default-sla',
    ]);

    SlaPolicy::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Brand Specific SLA',
        'slug' => 'brand-specific-sla',
    ]);

    SlaPolicy::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $otherBrand->id,
        'name' => 'Other Brand SLA',
        'slug' => 'other-brand-sla',
    ]);

    actingAs($admin);
    confirmTwoFactor($admin);

    $allResponse = getJson('/api/v1/sla-policies', slaHeaders($tenant));
    $allResponse->assertOk();
    $allResponse->assertJsonCount(2, 'data');

    $brandResponse = getJson('/api/v1/sla-policies?brand_id='.$brand->id, slaHeaders($tenant, $brand));
    $brandResponse->assertOk();
    $brandResponse->assertJsonCount(1, 'data');
    $brandResponse->assertJsonPath('data.0.name', 'Brand Specific SLA');

    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 enforces SLA policy RBAC matrix', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $policy = SlaPolicy::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Matrix Policy',
        'slug' => 'matrix-policy',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    confirmTwoFactor($admin);
    getJson('/api/v1/sla-policies/'.$policy->id, slaHeaders($tenant, $brand))->assertOk();
    patchJson('/api/v1/sla-policies/'.$policy->id, [
        'default_first_response_minutes' => 90,
    ], slaHeaders($tenant, $brand))->assertOk();

    actingAs($agent);
    confirmTwoFactor($agent);
    getJson('/api/v1/sla-policies/'.$policy->id, slaHeaders($tenant, $brand))->assertOk();
    patchJson('/api/v1/sla-policies/'.$policy->id, [
        'default_first_response_minutes' => 30,
    ], slaHeaders($tenant, $brand))->assertForbidden();

    actingAs($viewer);
    confirmTwoFactor($viewer);
    getJson('/api/v1/sla-policies/'.$policy->id, slaHeaders($tenant, $brand))->assertOk();
    patchJson('/api/v1/sla-policies/'.$policy->id, [
        'default_first_response_minutes' => 45,
    ], slaHeaders($tenant, $brand))->assertForbidden();

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 isolates SLA policies across tenants', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $otherPolicy = SlaPolicy::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'name' => 'Other Tenant SLA',
        'slug' => 'other-tenant-sla',
    ]);

    actingAs($admin);
    confirmTwoFactor($admin);

    $response = getJson('/api/v1/sla-policies/'.$otherPolicy->id, slaHeaders($tenant, $brand));
    $response->assertNotFound();

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});

it('E4-F3-I2 calculates SLA deadlines respecting timezone and holidays', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $policy = SlaPolicy::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'timezone' => 'America/New_York',
        'business_hours' => [
            ['day' => 'friday', 'start' => '09:00', 'end' => '17:00'],
            ['day' => 'monday', 'start' => '09:00', 'end' => '17:00'],
            ['day' => 'tuesday', 'start' => '09:00', 'end' => '17:00'],
        ],
        'holiday_exceptions' => [
            ['date' => '2025-07-07', 'name' => 'Observed Holiday'],
        ],
        'default_first_response_minutes' => 120,
        'default_resolution_minutes' => 480,
    ]);

    $policy->targets()->create([
        'channel' => Ticket::CHANNEL_EMAIL,
        'priority' => 'high',
        'first_response_minutes' => 120,
        'resolution_minutes' => 480,
        'use_business_hours' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);
    confirmTwoFactor($admin);

    $workflow = TicketWorkflow::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Support Workflow',
        'slug' => 'support-workflow',
    ]);

    TicketWorkflowState::factory()->initial()->create([
        'ticket_workflow_id' => $workflow->id,
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Open',
        'slug' => 'open',
        'position' => 1,
        'sla_minutes' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2025-07-04 20:00:00', 'UTC'));

    /** @var TicketService $ticketService */
    $ticketService = app(TicketService::class);

    $ticket = $ticketService->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'subject' => 'Holiday escalation',
        'status' => 'open',
        'priority' => 'high',
        'channel' => Ticket::CHANNEL_EMAIL,
    ], $admin);

    expect($ticket->sla_policy_id)->toEqual($policy->getKey());

    $ticket->refresh();

    expect($ticket->first_response_due_at?->toIso8601String())->toEqual('2025-07-08T14:00:00+00:00');
    expect($ticket->resolution_due_at?->toIso8601String())->toEqual('2025-07-08T20:00:00+00:00');
    expect($ticket->sla_due_at?->toIso8601String())->toEqual('2025-07-08T20:00:00+00:00');

    Carbon::setTestNow();

    app()->forgetInstance('currentBrand');
    app()->forgetInstance('currentTenant');
});
