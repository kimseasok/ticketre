<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
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

it('E2-F3-I1 allows admins to list permissions', function () {
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

    $response = getJson('/api/v1/permissions', permissionHeaders($tenant));

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'name', 'slug', 'is_system']]]);
});

it('E2-F3-I1 denies agents from listing permissions', function () {
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

    $response = getJson('/api/v1/permissions', permissionHeaders($tenant));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F3-I1 creates permissions with audit logs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'custom.permission',
        'description' => 'Scoped to brand',
        'brand_id' => $brand->id,
    ];

    $response = postJson('/api/v1/permissions', $payload, permissionHeaders($tenant, $brand));

    $response->assertCreated();
    $permissionId = $response->json('data.id');

    expect(Permission::query()->where('id', $permissionId)->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('auditable_type', Permission::class)
        ->where('auditable_id', $permissionId)
        ->where('action', 'permission.created')
        ->first();

    expect($log)->not->toBeNull();
});

it('E2-F3-I1 validates brand ownership when creating permissions', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $foreignBrand = Brand::factory()->create();

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/permissions', [
        'name' => 'invalid.brand',
        'brand_id' => $foreignBrand->id,
    ], permissionHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F3-I1 updates permission attributes', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $create = postJson('/api/v1/permissions', [
        'name' => 'updatable.permission',
        'description' => 'initial',
    ], permissionHeaders($tenant, $brand));

    $permissionId = $create->json('data.id');

    $response = patchJson('/api/v1/permissions/'.$permissionId, [
        'description' => 'updated description',
    ], permissionHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.description', 'updated description');
});

it('E2-F3-I1 prevents deleting system permissions', function () {
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

    $systemPermission = Permission::query()->where('tenant_id', $tenant->id)->firstOrFail();

    $response = deleteJson('/api/v1/permissions/'.$systemPermission->id, [], permissionHeaders($tenant));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_PERMISSION_PROTECTED');
});

it('E2-F3-I1 allows deleting custom permissions', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $create = postJson('/api/v1/permissions', [
        'name' => 'deletable.permission',
    ], permissionHeaders($tenant, $brand));

    $permissionId = $create->json('data.id');

    $response = deleteJson('/api/v1/permissions/'.$permissionId, [], permissionHeaders($tenant));

    $response->assertNoContent();
    expect(Permission::query()->where('id', $permissionId)->exists())->toBeFalse();
});

it('E2-F3-I1 enforces tenant isolation on show', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    app()->instance('currentTenant', $tenantA);
    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    actingAs($adminA);

    $foreignPermission = postJson('/api/v1/permissions', [
        'name' => 'isolated.permission',
    ], permissionHeaders($tenantA, $brandA))->json('data.id');

    app()->instance('currentTenant', $tenantB);
    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $adminB->assignRole('Admin');

    actingAs($adminB);

    $response = getJson('/api/v1/permissions/'.$foreignPermission, permissionHeaders($tenantB));

    $response->assertNotFound();
});

it('E2-F3-I1 enforces policy matrix for creating permissions', function (string $role, int $status) {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole($role);

    actingAs($user);

    $response = postJson('/api/v1/permissions', [
        'name' => 'matrix.permission.'.$role,
    ], permissionHeaders($tenant, $brand));

    $response->assertStatus($status);
})->with([
    'admin' => ['Admin', 201],
    'agent' => ['Agent', 403],
    'viewer' => ['Viewer', 403],
]);

it('E2-F3-I1 returns error schema for invalid payloads', function () {
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

    $response = postJson('/api/v1/permissions', [], permissionHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});
