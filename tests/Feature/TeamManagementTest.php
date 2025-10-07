<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TeamService;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function teamHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F4-I1 allows admins to create teams with audit logging', function () {
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

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($admin);

    $payload = [
        'name' => 'Tier 1 Support',
        'brand_id' => $brand->id,
        'default_queue' => 'general',
        'description' => 'Handles first-response tickets.',
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Lead', 'is_primary' => true],
            ['user_id' => $agent->id, 'role' => 'Agent', 'is_primary' => true],
        ],
    ];

    $response = postJson('/api/v1/teams', $payload, teamHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Tier 1 Support');
    $response->assertJsonCount(2, 'data.members');

    $teamId = $response->json('data.id');

    expect(Team::query()->whereKey($teamId)->exists())->toBeTrue();

    $audit = AuditLog::query()
        ->where('auditable_type', Team::class)
        ->where('auditable_id', $teamId)
        ->where('action', 'team.created')
        ->first();

    expect($audit)->not->toBeNull();
});

it('E2-F4-I1 validates duplicate members when creating teams', function () {
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

    $response = postJson('/api/v1/teams', [
        'name' => 'Escalations',
        'brand_id' => $brand->id,
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Lead', 'is_primary' => true],
            ['user_id' => $admin->id, 'role' => 'Backup', 'is_primary' => false],
        ],
    ], teamHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F4-I1 denies viewers from creating teams', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/teams', [
        'name' => 'Unauthorized Team',
    ], teamHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F4-I1 allows agents to list brand-scoped teams only', function () {
    $tenant = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'brand-a']);
    $brandB = Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'brand-b']);

    app()->instance('currentTenant', $tenant);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brandA->id,
    ]);
    $admin->assignRole('Admin');

    $agentBrandA = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brandA->id,
    ]);
    $agentBrandA->assignRole('Agent');

    $agentBrandB = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brandB->id,
    ]);
    $agentBrandB->assignRole('Agent');

    /** @var TeamService $teamService */
    $teamService = app(TeamService::class);

    app()->instance('currentBrand', $brandA);
    $teamService->create([
        'name' => 'Brand A Support',
        'brand_id' => $brandA->id,
        'default_queue' => 'general',
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Lead', 'is_primary' => true],
            ['user_id' => $agentBrandA->id, 'role' => 'Agent', 'is_primary' => true],
        ],
    ], $admin);

    app()->instance('currentBrand', $brandB);
    $teamService->create([
        'name' => 'Brand B Support',
        'brand_id' => $brandB->id,
        'default_queue' => 'vip',
        'members' => [
            ['user_id' => $agentBrandB->id, 'role' => 'Agent', 'is_primary' => true],
        ],
    ], $admin);

    actingAs($agentBrandA);

    app()->instance('currentBrand', $brandA);
    $responseA = getJson('/api/v1/teams', teamHeaders($tenant, $brandA));
    $responseA->assertOk();
    $responseA->assertJsonCount(1, 'data');
    $responseA->assertJsonPath('data.0.name', 'Brand A Support');

    actingAs($agentBrandB);

    app()->instance('currentBrand', $brandB);
    $responseB = getJson('/api/v1/teams', teamHeaders($tenant, $brandB));
    $responseB->assertOk();
    $responseB->assertJsonCount(1, 'data');
    $responseB->assertJsonPath('data.0.name', 'Brand B Support');
});

it('E2-F4-I1 prevents cross-tenant access to teams', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);

    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $adminB->assignRole('Admin');

    /** @var TeamService $teamService */
    $teamService = app(TeamService::class);

    $team = $teamService->create([
        'name' => 'Tenant A Support',
        'brand_id' => $brandA->id,
        'members' => [
            ['user_id' => $adminA->id, 'role' => 'Lead', 'is_primary' => true],
        ],
    ], $adminA);

    actingAs($adminB);

    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);

    $response = getJson('/api/v1/teams/'.$team->id, teamHeaders($tenantB, $brandB));
    $response->assertNotFound();
});

it('E2-F4-I1 updates team metadata and membership with audit logging', function () {
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

    /** @var TeamService $teamService */
    $teamService = app(TeamService::class);

    $team = $teamService->create([
        'name' => 'Incident Response',
        'brand_id' => $brand->id,
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Commander', 'is_primary' => true],
            ['user_id' => $agent->id, 'role' => 'Responder', 'is_primary' => true],
        ],
    ], $admin);

    actingAs($admin);

    $response = patchJson('/api/v1/teams/'.$team->id, [
        'default_queue' => 'incidents',
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Commander', 'is_primary' => true],
            ['user_id' => $viewer->id, 'role' => 'Observer', 'is_primary' => false],
        ],
    ], teamHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.default_queue', 'incidents');
    $response->assertJsonCount(2, 'data.members');
    $response->assertJsonPath('data.members.1.user_id', $viewer->id);

    $audit = AuditLog::query()
        ->where('auditable_type', Team::class)
        ->where('auditable_id', $team->id)
        ->where('action', 'team.updated')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
});

it('E2-F4-I1 responds with 401 for unauthenticated team listing', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $response = getJson('/api/v1/teams', teamHeaders($tenant, $brand));

    $response->assertUnauthorized();
    $response->assertJsonPath('message', 'Unauthenticated.');
});

it('E2-F4-I1 deletes teams and memberships with audit entry', function () {
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

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    /** @var TeamService $teamService */
    $teamService = app(TeamService::class);

    $team = $teamService->create([
        'name' => 'Decommission Team',
        'brand_id' => $brand->id,
        'members' => [
            ['user_id' => $admin->id, 'role' => 'Supervisor', 'is_primary' => true],
            ['user_id' => $agent->id, 'role' => 'Specialist', 'is_primary' => false],
        ],
    ], $admin);

    actingAs($admin);

    $response = deleteJson('/api/v1/teams/'.$team->id, [], teamHeaders($tenant, $brand));
    $response->assertNoContent();

    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();

    $audit = AuditLog::query()
        ->where('auditable_type', Team::class)
        ->where('auditable_id', $team->id)
        ->where('action', 'team.deleted')
        ->first();

    expect($audit)->not->toBeNull();
});
