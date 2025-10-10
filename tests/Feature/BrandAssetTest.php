<?php

use App\Models\Brand;
use App\Models\BrandAsset;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function brandAssetHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function brandAssetVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E9-F4-I2 #417 allows admins to create brand assets via API', function (): void {
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
    brandAssetVerifyTwoFactor($admin);

    $payload = [
        'brand_id' => $brand->id,
        'type' => 'primary_logo',
        'path' => 'brands/'.$brand->slug.'/logo.png',
        'disk' => 'public',
        'content_type' => 'image/png',
        'size' => 102400,
        'cache_control' => 'public, max-age=604800',
    ];

    $response = postJson('/api/v1/brand-assets', $payload, array_merge(brandAssetHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.type', 'primary_logo');
    $response->assertJsonPath('data.attributes.version', 1);

    expect(BrandAsset::where('brand_id', $brand->id)->where('type', 'primary_logo')->exists())->toBeTrue();
});

it('E9-F4-I2 #417 prevents viewers from creating brand assets', function (): void {
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
    brandAssetVerifyTwoFactor($viewer);

    $response = postJson('/api/v1/brand-assets', [
        'brand_id' => $brand->id,
        'type' => 'primary_logo',
        'path' => 'brands/'.$brand->slug.'/logo.png',
    ], brandAssetHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E9-F4-I2 #417 scopes brand asset listings to tenant and role permissions', function (): void {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    brandAssetVerifyTwoFactor($agent);

    BrandAsset::factory()->forBrand($brand)->create(['type' => 'primary_logo', 'version' => 1]);
    BrandAsset::factory()->forBrand($otherBrand)->create(['type' => 'primary_logo', 'version' => 1]);

    $response = getJson('/api/v1/brand-assets', brandAssetHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.attributes.brand_id', $brand->id);
});

it('E9-F4-I2 #417 delivers brand assets with caching headers', function (): void {
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
    brandAssetVerifyTwoFactor($admin);

    $asset = BrandAsset::factory()->forBrand($brand)->create([
        'type' => 'favicon',
        'version' => 2,
        'cache_control' => 'public, max-age=3600',
    ]);

    $response = getJson('/api/v1/brand-assets/'.$asset->getKey().'/deliver', array_merge(brandAssetHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertOk();
    $cacheHeader = $response->headers->get('Cache-Control');
    expect($cacheHeader)->not->toBeNull();
    expect($cacheHeader)->toContain('max-age=3600');
    expect($cacheHeader)->toContain('public');
    $response->assertHeader('X-Brand-Asset-Version', '2');
    $response->assertJsonPath('data.version', 2);
});

it('E9-F4-I2 #417 returns validation error for unsupported asset types', function (): void {
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
    brandAssetVerifyTwoFactor($admin);

    $response = postJson('/api/v1/brand-assets', [
        'brand_id' => $brand->id,
        'type' => 'unsupported-type',
        'path' => 'brands/'.$brand->slug.'/logo.png',
    ], brandAssetHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E9-F4-I2 #417 exposes theme configuration with fallbacks when assets missing', function (): void {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create([
        'tenant_id' => $tenant->id,
        'theme' => [
            'primary' => '#111111',
            'secondary' => '#222222',
        ],
        'primary_logo_path' => null,
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
    brandAssetVerifyTwoFactor($viewer);

    $response = getJson('/api/v1/brands/'.$brand->getKey().'/theme', brandAssetHeaders($tenant, $brand));

    $response->assertOk();
    $cacheHeader = $response->headers->get('Cache-Control');
    expect($cacheHeader)->not->toBeNull();
    expect($cacheHeader)->toContain('max-age=604800');
    expect($cacheHeader)->toContain('public');
    $response->assertJsonPath('data.colors.primary', '#111111');
    $response->assertJsonPath('data.assets.primary_logo', null);
});
