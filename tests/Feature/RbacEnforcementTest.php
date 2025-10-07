<?php

use App\Models\AccessAttempt;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

function rbacHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F3-I3 allows admins to access RBAC-protected APIs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = getJson('/api/v1/roles', rbacHeaders($tenant, $brand));

    $response->assertOk();
    expect(AccessAttempt::query()->count())->toBe(0);
});

it('E2-F3-I3 denies viewers without permissions and records an access attempt', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    AccessAttempt::query()->delete();

    $response = getJson('/api/v1/roles', rbacHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');

    $attempt = AccessAttempt::query()->latest()->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->permission)->toBe('roles.view')
        ->and($attempt->reason)->toBe('insufficient_permission')
        ->and($attempt->tenant_id)->toBe($tenant->id);
});

it('E2-F3-I3 blocks tenant mismatches with structured errors', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    AccessAttempt::query()->delete();

    $response = getJson('/api/v1/roles', rbacHeaders($otherTenant));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');

    $attempt = AccessAttempt::query()->latest()->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->reason)->toBe('tenant_mismatch')
        ->and($attempt->tenant_id)->toBe($admin->tenant_id);
});

it('E2-F3-I3 enforces Filament RBAC using middleware', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer, 'web');

    AccessAttempt::query()->delete();

    $response = get('/admin/roles');

    $response->assertForbidden();

    $attempt = AccessAttempt::query()->latest()->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->permission)->toBe('admin.access')
        ->and($attempt->reason)->toBe('insufficient_permission')
        ->and($attempt->route)->toContain('filament');
});
