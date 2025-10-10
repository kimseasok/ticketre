<?php

use App\Models\Brand;
use App\Models\BrandDomain;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\BrandDomainProbe;
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

function brandApiHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function brandVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E9-F4-I3 #418 allows admins to create brands via API', function () {
    $tenant = Tenant::factory()->create();
    $existingBrand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $existingBrand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $existingBrand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $existingBrand->id,
        'user_id' => $admin->id,
    ]);
    brandVerifyTwoFactor($admin);

    $payload = [
        'name' => 'Launch Brand',
        'slug' => 'launch-brand',
        'domain' => 'launch.'.$tenant->domain,
        'theme' => [
            'primary' => '#112233',
            'secondary' => '#445566',
            'accent' => '#778899',
            'text' => '#ffffff',
        ],
    ];

    $response = postJson('/api/v1/brands', $payload, array_merge(brandApiHeaders($tenant, $existingBrand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.slug', 'launch-brand');
    $response->assertJsonPath('data.attributes.theme.primary', '#112233');

    expect(Brand::where('slug', 'launch-brand')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E9-F4-I3 #418 prevents viewers from creating brands', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/brands', [
        'name' => 'Unauthorized Brand',
        'domain' => 'unauthorized.'.$tenant->domain,
    ], brandApiHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E9-F4-I3 #418 validates brand domains', function () {
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
    brandVerifyTwoFactor($admin);

    $response = postJson('/api/v1/brands', [
        'name' => 'Invalid Domain Brand',
        'domain' => 'invalid_domain',
    ], brandApiHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E9-F4-I3 #418 allows agents to list brands within their tenant', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'secondary-brand']);
    Brand::factory()->create(); // other tenant

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
    brandVerifyTwoFactor($agent);

    $response = getJson('/api/v1/brands', brandApiHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('E9-F4-I3 #418 allows admins to manage brand domains and queue verification', function () {
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
    brandVerifyTwoFactor($admin);

    app()->bind(BrandDomainProbe::class, function (): BrandDomainProbe {
        return new class extends BrandDomainProbe {
            public function checkDns(string $domain): array
            {
                return [
                    'verified' => true,
                    'records' => [
                        ['type' => 'CNAME', 'target' => strtolower((string) config('branding.verification.expected_cname'))],
                    ],
                    'error' => null,
                ];
            }

            public function checkSsl(string $domain): array
            {
                return [
                    'verified' => true,
                    'issuer' => 'DemoCA',
                    'error' => null,
                ];
            }
        };
    });

    $createResponse = postJson('/api/v1/brand-domains', [
        'brand_id' => $brand->id,
        'domain' => 'support.'.$tenant->domain,
    ], brandApiHeaders($tenant, $brand));

    $createResponse->assertCreated();
    $domainId = (int) $createResponse->json('data.id');

    $verifyResponse = postJson(
        "/api/v1/brand-domains/{$domainId}/verify",
        [],
        brandApiHeaders($tenant, $brand)
    );

    $verifyResponse->assertStatus(202);

    $domain = BrandDomain::findOrFail($domainId);
    expect($domain->status)->toBe('verified');
    expect($domain->ssl_status)->toBe('active');
});

it('E9-F4-I3 #418 enforces tenant isolation for brand domains', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);
    $domain = BrandDomain::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'domain' => 'isolated.'.$otherTenant->domain,
    ]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = getJson('/api/v1/brand-domains/'.$domain->getKey(), brandApiHeaders($tenant, $brand));

    $response->assertStatus(403);
});

it('E9-F4-I3 #418 prevents viewers from managing brand domains', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/brand-domains', [
        'brand_id' => $brand->id,
        'domain' => 'viewer-blocked.'.$tenant->domain,
    ], brandApiHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E9-F4-I3 #418 returns validation errors for duplicate brand domains', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $existingDomain = BrandDomain::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'domain' => 'duplicate.'.$tenant->domain,
    ]);

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
    brandVerifyTwoFactor($admin);

    $response = postJson('/api/v1/brand-domains', [
        'brand_id' => $brand->id,
        'domain' => $existingDomain->domain,
    ], brandApiHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});
