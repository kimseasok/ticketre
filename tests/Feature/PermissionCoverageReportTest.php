<?php

use App\Models\Brand;
use App\Models\PermissionCoverageReport;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\PermissionCoverageReportService;
use App\Services\RoutePermissionCoverageAnalyzer;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;

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

function coverageHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function coverageVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E10-F2-I2 #423 allows admins to generate permission coverage reports via API', function () {
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
    coverageVerifyTwoFactor($admin);

    $payload = [
        'module' => 'api',
        'notes' => 'NON-PRODUCTION validation run',
        'metadata' => [
            'pipeline' => 'nightly',
        ],
    ];

    $response = postJson('/api/v1/permission-coverage-reports', $payload, array_merge(coverageHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.type', 'permission-coverage-reports');
    $response->assertJsonPath('data.attributes.module', 'api');
    $response->assertJsonStructure(['data' => ['attributes' => ['coverage', 'unguarded_routes', 'total_routes']]]);

    expect(PermissionCoverageReport::where('tenant_id', $tenant->id)->where('module', 'api')->exists())->toBeTrue();
});

it('E10-F2-I2 #423 prevents viewers from managing coverage reports', function () {
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
    coverageVerifyTwoFactor($viewer);

    $response = postJson('/api/v1/permission-coverage-reports', [
        'module' => 'api',
    ], coverageHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E10-F2-I2 #423 validates incoming module and metadata payloads', function () {
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
    coverageVerifyTwoFactor($admin);

    $response = postJson('/api/v1/permission-coverage-reports', [
        'module' => 'unknown-module',
        'metadata' => 'not-an-array',
    ], coverageHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E10-F2-I2 #423 enforces tenant isolation in listings', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    $adminB->assignRole('Admin');
    actingAs($adminB);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'user_id' => $adminB->id,
    ]);
    coverageVerifyTwoFactor($adminB);

    app(PermissionCoverageReportService::class)->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'module' => 'api',
        'notes' => 'Tenant B seed',
    ], $adminB);

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');
    actingAs($adminA);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
        'user_id' => $adminA->id,
    ]);
    coverageVerifyTwoFactor($adminA);

    $response = getJson('/api/v1/permission-coverage-reports', coverageHeaders($tenantA, $brandA));

    $response->assertOk();
    $response->assertJsonMissing(['notes' => 'Tenant B seed']);
});

it('E10-F2-I2 #423 honours policy matrix for admin, agent, and viewer roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    coverageVerifyTwoFactor($admin);

    $report = app(PermissionCoverageReportService::class)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'module' => 'api',
        'notes' => 'Policy matrix seed',
    ], $admin);

    actingAs($agent);
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    coverageVerifyTwoFactor($agent);
    $responseAgent = getJson('/api/v1/permission-coverage-reports/'.$report->getKey(), coverageHeaders($tenant, $brand));
    $responseAgent->assertOk();

    $createAgent = postJson('/api/v1/permission-coverage-reports', ['module' => 'admin'], coverageHeaders($tenant, $brand));
    $createAgent->assertStatus(403);

    actingAs($viewer);
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);
    coverageVerifyTwoFactor($viewer);
    $viewResponse = getJson('/api/v1/permission-coverage-reports/'.$report->getKey(), coverageHeaders($tenant, $brand));
    $viewResponse->assertOk();

    $deleteResponse = deleteJson('/api/v1/permission-coverage-reports/'.$report->getKey(), [], coverageHeaders($tenant, $brand));
    $deleteResponse->assertStatus(403);
    $deleteResponse->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E10-F2-I2 #423 exposes correlation metadata on update responses', function () {
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
    coverageVerifyTwoFactor($admin);

    $report = app(PermissionCoverageReportService::class)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'module' => 'api',
    ], $admin);

    $correlation = Str::uuid()->toString();
    $response = patchJson(
        '/api/v1/permission-coverage-reports/'.$report->getKey(),
        ['notes' => 'NON-PRODUCTION patch'],
        array_merge(coverageHeaders($tenant, $brand), ['X-Correlation-ID' => $correlation])
    );

    $response->assertOk();
    $response->assertJsonPath('meta.correlation_id', $correlation);
    $response->assertHeader('X-Correlation-ID');
});

it('E10-F2-I2 #423 reports zero unguarded routes for critical modules', function () {
    $analyzer = app(RoutePermissionCoverageAnalyzer::class);

    $results = $analyzer->analyze();

    expect($results['api']['unguarded_routes'])->toBe(0);
    expect($results['portal']['unguarded_routes'])->toBe(0);
    expect($results['admin']['unguarded_routes'])->toBe(0);
});
