<?php

use App\Models\Brand;
use App\Models\CiQualityGate;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
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

function qualityGateHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function verifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E11-F5-I2 allows admins to create CI quality gates via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    expect($admin->fresh()->can('ci.quality_gates.manage'))->toBeTrue();
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    verifyTwoFactor($admin);

    $payload = [
        'name' => 'Critical Path Gate',
        'slug' => 'critical-path-gate',
        'coverage_threshold' => 90,
        'max_critical_vulnerabilities' => 0,
        'max_high_vulnerabilities' => 1,
        'enforce_dependency_audit' => true,
        'enforce_docker_build' => true,
        'notifications_enabled' => true,
        'notify_channel' => '#alerts',
        'metadata' => [
            'owner' => 'platform',
            'description' => 'NON-PRODUCTION sample gate.',
        ],
    ];

    $response = postJson('/api/v1/ci-quality-gates', $payload, array_merge(qualityGateHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.slug', 'critical-path-gate');
    $response->assertJsonPath('data.attributes.coverage_threshold', 90);

    expect(CiQualityGate::where('slug', 'critical-path-gate')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E11-F5-I2 prevents viewers from managing CI quality gates', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/ci-quality-gates', [
        'name' => 'Unauthorized Gate',
        'coverage_threshold' => 80,
        'max_critical_vulnerabilities' => 0,
        'max_high_vulnerabilities' => 0,
    ], qualityGateHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F5-I2 validates coverage threshold range', function () {
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
    verifyTwoFactor($admin);

    $response = postJson('/api/v1/ci-quality-gates', [
        'name' => 'Invalid Gate',
        'coverage_threshold' => 150,
        'max_critical_vulnerabilities' => 0,
        'max_high_vulnerabilities' => 0,
    ], qualityGateHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E11-F5-I2 allows agents to list CI quality gates with tenant scope', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $gate = CiQualityGate::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'agent-visible-gate',
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    expect($agent->fresh()->can('ci.quality_gates.view'))->toBeTrue();
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    verifyTwoFactor($agent);

    $response = getJson('/api/v1/ci-quality-gates', qualityGateHeaders($tenant, $brand));
    $response->assertOk();
    $response->assertJsonFragment(['slug' => 'agent-visible-gate']);
});

it('E11-F5-I2 enforces tenant isolation for CI quality gates', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $gate = CiQualityGate::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'isolated-gate',
    ]);

    $admin = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $admin->assignRole('Admin');

    app()->instance('currentTenant', $otherTenant);
    app()->forgetInstance('currentBrand');

    actingAs($admin);

    $response = getJson('/api/v1/ci-quality-gates/'.$gate->getKey(), qualityGateHeaders($otherTenant));
    $response->assertStatus(404);
});

it('E11-F5-I2 allows admins to delete CI quality gates', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $gate = CiQualityGate::factory()->create([
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
    verifyTwoFactor($admin);

    $response = deleteJson('/api/v1/ci-quality-gates/'.$gate->getKey(), [], qualityGateHeaders($tenant, $brand));
    $response->assertNoContent();
    expect(CiQualityGate::withTrashed()->find($gate->getKey())->trashed())->toBeTrue();
});

it('E11-F5-I2 updates CI quality gates with audit-friendly digests', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $gate = CiQualityGate::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'notify_channel' => '#ci-alerts',
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    verifyTwoFactor($admin);

    $response = patchJson('/api/v1/ci-quality-gates/'.$gate->getKey(), [
        'notify_channel' => '#platform-updates',
    ], qualityGateHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.attributes.notify_channel_digest', hash('sha256', '#platform-updates'));
});

it('E11-F5-I2 enforces quality gate command thresholds (success)', function () {
    $tenant = Tenant::factory()->create(['slug' => 'tenant-ci']);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'brand-ci']);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $gate = CiQualityGate::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'pipeline',
        'coverage_threshold' => 80,
        'max_critical_vulnerabilities' => 0,
        'max_high_vulnerabilities' => 1,
    ]);

    $status = artisan('ci:enforce-quality-gate', [
        '--gate' => $gate->slug,
        '--tenant' => $tenant->slug,
        '--brand' => $brand->slug,
        '--coverage' => 92,
        '--critical' => 0,
        '--high' => 1,
        '--correlation' => 'E11-F5-I2-success',
    ])->run();

    expect($status)->toBe(Command::SUCCESS);
});

it('E11-F5-I2 fails quality gate command when thresholds are violated', function () {
    $tenant = Tenant::factory()->create(['slug' => 'tenant-ci-fail']);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'brand-ci-fail']);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $gate = CiQualityGate::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'pipeline-fail',
        'coverage_threshold' => 95,
        'max_critical_vulnerabilities' => 0,
        'max_high_vulnerabilities' => 0,
    ]);

    $status = artisan('ci:enforce-quality-gate', [
        '--gate' => $gate->slug,
        '--tenant' => $tenant->slug,
        '--brand' => $brand->slug,
        '--coverage' => 80,
        '--critical' => 1,
        '--high' => 2,
        '--correlation' => 'E11-F5-I2-failure',
    ])->run();

    expect($status)->toBe(Command::FAILURE);
});
