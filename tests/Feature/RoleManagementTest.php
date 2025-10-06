<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function roleHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F2-I1 provisions system roles for new tenants automatically', function () {
    $tenant = Tenant::factory()->create();

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $roles = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('slug');

    expect($roles)->toContain('admin', 'agent', 'viewer');
});

it('E2-F2-I1 allows admins to list tenant roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = getJson('/api/v1/roles', roleHeaders($tenant));

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
});

it('E2-F2-I1 denies agents from listing roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = getJson('/api/v1/roles', roleHeaders($tenant));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F2-I1 creates custom roles with audit logs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'Quality Analyst',
        'description' => 'Monitors QA workflows',
        'permissions' => ['tickets.view', 'reports.view'],
    ];

    $response = postJson('/api/v1/roles', $payload, roleHeaders($tenant));
    $response->assertCreated();

    $roleId = $response->json('data.id');

    expect(Role::query()->where('id', $roleId)->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('auditable_type', Role::class)
        ->where('auditable_id', $roleId)
        ->where('action', 'role.created')
        ->first();

    expect($log)->not->toBeNull();
});

it('E2-F2-I1 validates permissions when creating roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/roles', [
        'name' => 'Invalid Role',
        'permissions' => ['unknown.permission'],
    ], roleHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F2-I1 updates role attributes and permissions', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $role = Role::query()->where('slug', 'agent')->firstOrFail();

    $response = patchJson('/api/v1/roles/'.$role->id, [
        'description' => 'Handles escalations',
        'permissions' => ['tickets.view'],
    ], roleHeaders($tenant));

    $response->assertOk();
    $response->assertJsonPath('data.description', 'Handles escalations');
    $response->assertJsonPath('data.permissions', ['tickets.view']);

    $log = AuditLog::query()
        ->where('auditable_type', Role::class)
        ->where('auditable_id', $role->id)
        ->where('action', 'role.updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
});

it('E2-F2-I1 prevents deleting system roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $role = Role::query()->where('slug', 'admin')->firstOrFail();

    $response = deleteJson('/api/v1/roles/'.$role->id, [], roleHeaders($tenant));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_ROLE_PROTECTED');
});

it('E2-F2-I1 allows deleting custom roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/roles', [
        'name' => 'Temporary Role',
        'permissions' => ['tickets.view'],
    ], roleHeaders($tenant));

    $roleId = $response->json('data.id');

    $deleteResponse = deleteJson('/api/v1/roles/'.$roleId, [], roleHeaders($tenant));
    $deleteResponse->assertNoContent();

    expect(Role::query()->where('id', $roleId)->exists())->toBeFalse();
});

it('E2-F2-I1 isolates roles per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);
    app()->instance('currentTenant', $tenantB);

    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $adminB->assignRole('Admin');

    $foreignRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $tenantA->id)
        ->where('slug', 'agent')
        ->firstOrFail();

    actingAs($adminB);

    $response = getJson('/api/v1/roles/'.$foreignRole->id, roleHeaders($tenantB));

    $response->assertStatus(404);
});

it('E2-F2-I1 enforces policy matrix for create', function (string $roleName, int $expectedStatus) {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole($roleName);

    actingAs($user);

    $response = postJson('/api/v1/roles', [
        'name' => 'Matrix Test',
        'permissions' => ['tickets.view'],
    ], roleHeaders($tenant));

    $response->assertStatus($expectedStatus);
})->with([
    'admin' => ['Admin', 201],
    'agent' => ['Agent', 403],
    'viewer' => ['Viewer', 403],
]);
