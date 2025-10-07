<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BroadcastConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
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

function broadcastHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E1-F8-I1 creates and manages broadcast connections via API', function () {
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
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
        'connection_id' => 'conn-demo-123',
        'channel_name' => sprintf('private-tenants.%d.brands.%d.tickets', $tenant->id, $brand->id),
        'status' => BroadcastConnection::STATUS_ACTIVE,
        'latency_ms' => 48,
        'last_seen_at' => now()->toIso8601String(),
        'metadata' => ['client' => 'laravel-echo'],
    ];

    $create = postJson(
        '/api/v1/broadcast-connections',
        $payload,
        array_merge(broadcastHeaders($tenant, $brand), ['X-Correlation-ID' => 'conn-create-demo'])
    );

    $create->assertCreated();
    $create->assertHeader('X-Correlation-ID', 'conn-create-demo');
    $create->assertJsonPath('data.attributes.connection_id', 'conn-demo-123');

    $connection = BroadcastConnection::query()->where('connection_id', 'conn-demo-123')->firstOrFail();
    expect($connection->correlation_id)->toBe('conn-create-demo');

    $audit = AuditLog::query()->where('action', 'broadcast_connection.created')->first();
    expect($audit)->not->toBeNull();

    $update = patchJson(
        sprintf('/api/v1/broadcast-connections/%d', $connection->id),
        ['status' => BroadcastConnection::STATUS_STALE, 'latency_ms' => 72],
        array_merge(broadcastHeaders($tenant, $brand), ['X-Correlation-ID' => 'conn-update-demo'])
    );

    $update->assertOk();
    $update->assertHeader('X-Correlation-ID', 'conn-update-demo');
    $update->assertJsonPath('data.attributes.status', BroadcastConnection::STATUS_STALE);

    $connection->refresh();
    expect($connection->status)->toBe(BroadcastConnection::STATUS_STALE);

    $index = getJson('/api/v1/broadcast-connections', broadcastHeaders($tenant, $brand));
    $index->assertOk();
    $index->assertJsonPath('data.0.attributes.connection_id', 'conn-demo-123');

    $delete = deleteJson(
        sprintf('/api/v1/broadcast-connections/%d', $connection->id),
        [],
        array_merge(broadcastHeaders($tenant, $brand), ['X-Correlation-ID' => 'conn-delete-demo'])
    );

    $delete->assertNoContent();
    $delete->assertHeader('X-Correlation-ID', 'conn-delete-demo');

    expect(BroadcastConnection::withTrashed()->whereKey($connection->id)->first())->not->toBeNull();
});

it('E1-F8-I1 validates broadcast connection payloads', function () {
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

    $response = postJson('/api/v1/broadcast-connections', [
        'status' => 'invalid',
    ], broadcastHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F8-I1 enforces broadcast connection RBAC policy matrix', function () {
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

    postJson('/api/v1/broadcast-connections', [
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
        'connection_id' => 'matrix-conn',
        'channel_name' => sprintf('private-tenants.%d.brands.%d.tickets', $tenant->id, $brand->id),
        'status' => BroadcastConnection::STATUS_ACTIVE,
    ], broadcastHeaders($tenant, $brand))->assertCreated();

    actingAs($agent);
    postJson('/api/v1/broadcast-connections', [
        'brand_id' => $brand->id,
        'connection_id' => 'matrix-conn-2',
        'channel_name' => sprintf('private-tenants.%d.brands.%d.tickets', $tenant->id, $brand->id),
        'status' => BroadcastConnection::STATUS_ACTIVE,
    ], broadcastHeaders($tenant, $brand))->assertForbidden();

    getJson('/api/v1/broadcast-connections', broadcastHeaders($tenant, $brand))->assertOk();

    actingAs($viewer);
    getJson('/api/v1/broadcast-connections', broadcastHeaders($tenant, $brand))->assertForbidden();
});

it('E1-F8-I1 enforces tenant isolation for broadcast connections', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    $adminB->assignRole('Admin');

    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);

    $foreignConnection = BroadcastConnection::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'user_id' => $adminB->id,
        'channel_name' => sprintf('private-tenants.%d.brands.%d.tickets', $tenantB->id, $brandB->id),
    ]);

    actingAs($adminA);
    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);

    $response = getJson(
        sprintf('/api/v1/broadcast-connections/%d', $foreignConnection->id),
        broadcastHeaders($tenantA, $brandA)
    );

    $response->assertNotFound();
});

it('E1-F8-I1 authenticates broadcast channels with tenant scope', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $channel = sprintf('private-tenants.%d.brands.%d.tickets', $tenant->id, $brand->id);

    $response = postJson('/api/v1/broadcasting/auth', [
        'channel_name' => $channel,
        'socket_id' => '123.456',
    ], array_merge(broadcastHeaders($tenant, $brand), ['X-Correlation-ID' => 'auth-success']));

    $response->assertOk();
    $response->assertHeader('X-Correlation-ID', 'auth-success');
    expect($response->json('auth'))->not->toBeNull();
});

it('E1-F8-I1 rejects unauthorized broadcast channel authentication', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $unauthorized = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($unauthorized);

    $channel = sprintf('private-tenants.%d.brands.%d.tickets', $tenant->id, $brand->id);

    $response = postJson('/api/v1/broadcasting/auth', [
        'channel_name' => $channel,
        'socket_id' => '321.654',
    ], array_merge(broadcastHeaders($tenant, $brand), ['X-Correlation-ID' => 'auth-fail']));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});
