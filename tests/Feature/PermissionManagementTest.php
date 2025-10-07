<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function permissionHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F3-I1 allows admins to create tenant permissions with audit logging', function () {
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

    $response = postJson('/api/v1/permissions', [
        'name' => 'Escalation Override',
        'description' => 'Allows dispatchers to bypass SLA escalations.',
    ], permissionHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Escalation Override');
    $response->assertJsonPath('data.slug', 'escalation-override');

    $permissionId = $response->json('data.id');

    expect(Permission::query()->whereKey($permissionId)->where('tenant_id', $tenant->id)->exists())->toBeTrue();

    $audit = AuditLog::query()
        ->where('action', 'permission.created')
        ->where('auditable_id', $permissionId)
        ->where('auditable_type', Permission::class)
        ->first();

    expect($audit)->not->toBeNull();
});

it('E2-F3-I1 validates duplicate permission names against system catalog', function () {
    $tenant = Tenant::factory()->create();

    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/permissions', [
        'name' => 'tickets.view',
    ], permissionHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F3-I1 enforces policy matrix across roles', function () {
    $tenant = Tenant::factory()->create();

    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create(['tenant_id' => $tenant->id]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    $adminList = getJson('/api/v1/permissions', permissionHeaders($tenant));
    $adminList->assertOk();

    actingAs($agent);
    $agentList = getJson('/api/v1/permissions', permissionHeaders($tenant));
    $agentList->assertOk();

    $agentCreate = postJson('/api/v1/permissions', [
        'name' => 'Agent Attempt',
    ], permissionHeaders($tenant));
    $agentCreate->assertForbidden();
    $agentCreate->assertJsonPath('error.code', 'ERR_HTTP_403');

    actingAs($viewer);
    $viewerList = getJson('/api/v1/permissions', permissionHeaders($tenant));
    $viewerList->assertForbidden();
    $viewerList->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F3-I1 isolates tenant-specific permissions', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app()->instance('currentTenant', $tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->assignRole('Admin');

    /** @var PermissionService $service */
    $service = app(PermissionService::class);
    $permission = $service->create([
        'name' => 'Tenant Scoped Permission',
        'description' => 'Visible only to tenant A.',
    ], $adminA);

    app()->instance('currentTenant', $tenantB);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $adminB->assignRole('Admin');

    actingAs($adminB);

    $response = getJson('/api/v1/permissions/'.$permission->getKey(), permissionHeaders($tenantB));
    $response->assertNotFound();
});

it('E2-F3-I1 prevents deleting system permissions', function () {
    $tenant = Tenant::factory()->create();

    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $systemPermission = Permission::query()->where('name', 'tickets.view')->firstOrFail();

    $response = deleteJson('/api/v1/permissions/'.$systemPermission->getKey(), [], permissionHeaders($tenant));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_IMMUTABLE_PERMISSION');
});

it('E2-F3-I1 exposes update errors for system permissions', function () {
    $tenant = Tenant::factory()->create();

    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $systemPermission = Permission::query()->where('name', 'tickets.manage')->firstOrFail();

    $response = patchJson('/api/v1/permissions/'.$systemPermission->getKey(), [
        'description' => 'Attempt to change system description.',
    ], permissionHeaders($tenant));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_IMMUTABLE_PERMISSION');
});
