<?php

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function defaultHeaders(Tenant $tenant, ?Brand $brand = null): array
{
    $headers = [
        'X-Tenant' => $tenant->slug,
    ];

    if ($brand) {
        $headers['X-Brand'] = $brand->slug;
    }

    return $headers;
}

function portalPayload(): array
{
    return [
        'name' => 'Customer Portal',
        'email' => 'customer@example.com',
        'subject' => 'Access Issue',
        'message' => 'I cannot access my dashboard.',
    ];
}

it('E2-F3-I3 allows system roles to access admin APIs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    getJson('/api/v1/tickets', defaultHeaders($tenant, $brand))
        ->assertOk()
        ->assertHeader('X-Correlation-ID');

    actingAs($agent);
    getJson('/api/v1/tickets', defaultHeaders($tenant, $brand))
        ->assertOk()
        ->assertHeader('X-Correlation-ID');

    actingAs($viewer);
    getJson('/api/v1/tickets', defaultHeaders($tenant, $brand))
        ->assertOk()
        ->assertHeader('X-Correlation-ID');
});

it('E2-F3-I3 blocks platform access without permission', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    actingAs($user);

    getJson('/api/v1/tickets', defaultHeaders($tenant, $brand))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ERR_HTTP_403')
        ->assertHeader('X-Correlation-ID');
});

it('E2-F3-I3 prevents spoofing tenant headers', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');

    actingAs($adminA);

    getJson('/api/v1/tickets', defaultHeaders($tenantB, $brandB))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F3-I3 allows guests to submit portal tickets', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    Notification::fake();

    postJson('/api/v1/portal/tickets', portalPayload(), defaultHeaders($tenant, $brand))
        ->assertCreated()
        ->assertHeader('X-Correlation-ID');
});

it('E2-F3-I3 enforces portal permission for authenticated users', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    actingAs($user);

    postJson('/api/v1/portal/tickets', portalPayload(), defaultHeaders($tenant, $brand))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ERR_HTTP_403');
});
