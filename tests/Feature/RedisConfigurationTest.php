<?php

use App\Cache\RedisFallbackStore;
use App\Models\Brand;
use App\Models\RedisConfiguration;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\RedisRuntimeConfigurator;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function redisHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function redisVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E11-F3-I2 #432 allows admins to create redis configurations via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    redisVerifyTwoFactor($admin);

    $payload = [
        'name' => 'Primary Redis Cluster',
        'cache_host' => '192.0.2.10',
        'cache_port' => 6380,
        'cache_database' => 2,
        'session_host' => '192.0.2.11',
        'session_port' => 6381,
        'session_database' => 3,
        'session_lifetime_minutes' => 90,
        'fallback_store' => 'file',
    ];

    $response = postJson('/api/v1/redis-configurations', $payload, array_merge(redisHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.cache_port', 6380);
    $response->assertJsonPath('data.attributes.session_lifetime_minutes', 90);
    $response->assertJsonPath('data.attributes.cache_host_digest', hash('sha256', '192.0.2.10:6380'));

    expect(RedisConfiguration::where('tenant_id', $tenant->id)->where('cache_port', 6380)->exists())->toBeTrue();
});

it('E11-F3-I2 #432 prevents viewers from managing redis configurations', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    actingAs($viewer);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);
    redisVerifyTwoFactor($viewer);

    $response = postJson('/api/v1/redis-configurations', [
        'name' => 'Unauthorized Cluster',
        'cache_host' => '192.0.2.12',
        'cache_port' => 6379,
        'cache_database' => 1,
        'session_host' => '192.0.2.12',
        'session_port' => 6379,
        'session_database' => 1,
        'session_lifetime_minutes' => 60,
        'fallback_store' => 'file',
    ], redisHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F3-I2 #432 validates fallback store options', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    redisVerifyTwoFactor($admin);

    $response = postJson('/api/v1/redis-configurations', [
        'name' => 'Invalid Fallback',
        'cache_host' => '192.0.2.13',
        'cache_port' => 6379,
        'cache_database' => 0,
        'session_host' => '192.0.2.13',
        'session_port' => 6379,
        'session_database' => 0,
        'session_lifetime_minutes' => 60,
        'fallback_store' => 'database',
    ], redisHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E11-F3-I2 #432 enforces tenant isolation for redis configurations', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);

    $foreign = RedisConfiguration::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'cache_host' => '192.0.2.20',
        'session_host' => '192.0.2.20',
    ]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    actingAs($viewer);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);
    redisVerifyTwoFactor($viewer);

    $response = getJson('/api/v1/redis-configurations/'.$foreign->getKey(), redisHeaders($tenant, $brand));
    $response->assertStatus(404);
});

it('E11-F3-I2 #432 applies runtime configuration with fallback when redis is unavailable', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    /** @var RedisConfiguration $configuration */
    $configuration = RedisConfiguration::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'cache_host' => '192.0.2.30',
        'cache_port' => 6390,
        'session_host' => '192.0.2.31',
        'session_port' => 6391,
        'session_lifetime_minutes' => 45,
        'fallback_store' => 'array',
    ]);

    app(RedisRuntimeConfigurator::class)->applyForTenant($tenant->id, $brand->id, Str::uuid()->toString());

    Cache::forgetDriver('redis-fallback');
    $repository = Cache::store('redis-fallback');
    $store = $repository->getStore();

    expect($store)->toBeInstanceOf(RedisFallbackStore::class);

    $repository->put('runtime-test', 'value', 2);
    expect($repository->get('runtime-test'))->toBe('value');

    travel(3)->seconds();

    expect($repository->get('runtime-test'))->toBeNull();
    expect($store->usingFallback())->toBeTrue();
    expect(config('session.driver'))->toBe('redis-fallback');
    expect(config('session.store'))->toBe('redis-fallback');
    expect(config('session.lifetime'))->toBe(45);
});

it('E11-F3-I2 #432 allows admins to update redis configurations', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $configuration = RedisConfiguration::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'session_lifetime_minutes' => 60,
        'fallback_store' => 'file',
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    redisVerifyTwoFactor($admin);

    $response = patchJson('/api/v1/redis-configurations/'.$configuration->getKey(), [
        'session_lifetime_minutes' => 120,
        'fallback_store' => 'array',
    ], redisHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.attributes.session_lifetime_minutes', 120);
    $response->assertJsonPath('data.attributes.fallback_store', 'array');
});

it('E11-F3-I2 #432 allows agents to list redis configurations within their brand', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $configuration = RedisConfiguration::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Agent Visible Cluster',
        'cache_host' => '192.0.2.40',
        'session_host' => '192.0.2.41',
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    redisVerifyTwoFactor($agent);

    $response = getJson('/api/v1/redis-configurations', redisHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Agent Visible Cluster']);
});

it('E11-F3-I2 #432 prevents agents from deleting redis configurations', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $configuration = RedisConfiguration::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    redisVerifyTwoFactor($agent);

    $response = deleteJson('/api/v1/redis-configurations/'.$configuration->getKey(), [], redisHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F3-I2 #432 allows admins to delete redis configurations via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $configuration = RedisConfiguration::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    redisVerifyTwoFactor($admin);

    $response = deleteJson('/api/v1/redis-configurations/'.$configuration->getKey(), [], redisHeaders($tenant, $brand));

    $response->assertNoContent();
    $trashed = RedisConfiguration::withTrashed()->find($configuration->getKey());
    expect($trashed)->not->toBeNull();
    expect($trashed->trashed())->toBeTrue();
});
