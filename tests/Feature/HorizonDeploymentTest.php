<?php

use App\Models\Brand;
use App\Models\HorizonDeployment;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
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

function horizonHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function provisionRoles(Tenant $tenant): void
{
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
}

function horizonVerifyTwoFactor(User $user, ?Tenant $tenant = null, ?Brand $brand = null): void
{
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant?->getKey() ?? $user->tenant_id,
        'brand_id' => $brand?->getKey() ?? $user->brand_id,
        'user_id' => $user->getKey(),
    ]);

    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E11-F2-I2 #429 allows admins to create horizon deployments via API', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);
    horizonVerifyTwoFactor($admin, $tenant, $brand);

    $payload = [
        'name' => 'Platform Horizon Deployment',
        'slug' => 'platform-horizon',
        'domain' => 'horizon.platform.test',
        'auth_guard' => 'admin',
        'horizon_connection' => 'sync',
        'uses_tls' => false,
        'supervisors' => [
            [
                'name' => 'app-supervisor',
                'connection' => 'sync',
                'queue' => ['default'],
                'balance' => 'auto',
                'min_processes' => 1,
                'max_processes' => 4,
                'timeout' => 90,
                'tries' => 2,
            ],
        ],
        'metadata' => [
            'owner' => 'platform-queues',
        ],
    ];

    $correlationId = (string) Str::uuid();

    $response = postJson(
        '/api/v1/horizon-deployments',
        $payload,
        array_merge(horizonHeaders($tenant, $brand), ['X-Correlation-ID' => $correlationId])
    );

    $response->assertCreated();
    $response->assertJsonPath('meta.correlation_id', $correlationId);
    $response->assertJsonPath('data.attributes.domain_digest', hash('sha256', 'horizon.platform.test'));
    $response->assertJsonPath('data.attributes.supervisors.0.queue.0', 'default');

    expect(HorizonDeployment::where('slug', 'platform-horizon')->where('tenant_id', $tenant->id)->exists())
        ->toBeTrue();
});

it('E11-F2-I2 #429 prevents agents from deleting horizon deployments', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var HorizonDeployment $deployment */
    $deployment = HorizonDeployment::factory()
        ->forBrand($brand)
        ->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'slug' => 'agent-test-deployment',
        ]);

    /** @var User $agent */
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);
    horizonVerifyTwoFactor($agent, $tenant, $brand);

    $response = deleteJson(
        '/api/v1/horizon-deployments/'.$deployment->getKey(),
        [],
        array_merge(horizonHeaders($tenant, $brand), ['X-Correlation-ID' => 'fixed-correlation'])
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
    $response->assertHeader('X-Correlation-ID', 'fixed-correlation');

    expect(HorizonDeployment::withTrashed()->find($deployment->getKey()))
        ->not()->toBeNull();
});

it('E11-F2-I2 #429 validates supervisor queue definitions', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);
    horizonVerifyTwoFactor($admin, $tenant, $brand);

    $payload = [
        'name' => 'Invalid Horizon',
        'domain' => 'invalid.horizon.test',
        'supervisors' => [
            [
                'name' => 'broken-supervisor',
                'queue' => [],
            ],
        ],
    ];

    $response = postJson('/api/v1/horizon-deployments', $payload, horizonHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');

    $details = $response->json('error.details');
    expect($details['supervisors.0.queue'][0] ?? null)->toBe('The supervisors.0.queue field is required.');

    expect(HorizonDeployment::count())->toBe(0);
});

it('E11-F2-I2 #429 enforces tenant isolation when fetching deployments', function () {
    /** @var Tenant $tenantA */
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    /** @var Tenant $tenantB */
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
    /** @var Brand $brandB */
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);
    provisionRoles($tenantB);

    /** @var HorizonDeployment $deployment */
    $deployment = HorizonDeployment::factory()->forBrand($brandB)->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'slug' => 'isolated-deployment',
    ]);

    /** @var User $adminA */
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => null]);
    provisionRoles($tenantA);
    $adminA->assignRole('Admin');

    app()->instance('currentTenant', $tenantA);
    app()->forgetInstance('currentBrand');

    actingAs($adminA);
    horizonVerifyTwoFactor($adminA, $tenantA, null);

    $response = getJson('/api/v1/horizon-deployments/'.$deployment->getKey(), horizonHeaders($tenantA));

    $response->assertNotFound();
});

it('E11-F2-I2 #429 returns summarized health across deployments', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var HorizonDeployment $healthy */
    $healthy = HorizonDeployment::factory()->forBrand($brand)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'healthy-horizon',
        'supervisors' => [
            [
                'name' => 'healthy-supervisor',
                'connection' => 'sync',
                'queue' => ['default'],
                'min_processes' => 1,
                'max_processes' => 2,
            ],
        ],
    ]);

    /** @var HorizonDeployment $degraded */
    $degraded = HorizonDeployment::factory()->forBrand($brand)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'degraded-horizon',
    ]);

    $degraded->update([
        'supervisors' => [
            [
                'name' => 'degraded-supervisor',
                'connection' => 'sync',
                'queue' => [],
                'min_processes' => 4,
                'max_processes' => 2,
            ],
        ],
    ]);

    /** @var User $viewer */
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);
    horizonVerifyTwoFactor($viewer, $tenant, $brand);

    $response = getJson(
        '/api/v1/horizon-deployments/health',
        array_merge(horizonHeaders($tenant, $brand), ['X-Correlation-ID' => 'health-summary'])
    );

    $response->assertOk();
    $response->assertHeader('X-Correlation-ID', 'health-summary');
    $response->assertJsonPath('status', 'degraded');
    $response->assertJsonCount(2, 'deployments');
    $response->assertJson(fn ($json) => $json
        ->where('deployments.0.slug', 'healthy-horizon')
        ->where('deployments.1.slug', 'degraded-horizon')
        ->etc()
    );

    $degraded->refresh();
    expect($degraded->last_health_status)->toBe('degraded');
    expect($healthy->fresh()->last_health_status)->toBe('ok');
});

it('E11-F2-I2 #429 exposes per-deployment health details', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var HorizonDeployment $deployment */
    $deployment = HorizonDeployment::factory()->forBrand($brand)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'single-health-horizon',
        'supervisors' => [
            [
                'name' => 'imbalanced-supervisor',
                'connection' => 'sync',
                'queue' => ['default'],
                'min_processes' => 5,
                'max_processes' => 2,
            ],
        ],
    ]);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);
    horizonVerifyTwoFactor($admin, $tenant, $brand);

    $response = getJson(
        '/api/v1/horizon-deployments/'.$deployment->getKey().'/health',
        array_merge(horizonHeaders($tenant, $brand), ['X-Correlation-ID' => 'deployment-health'])
    );

    $response->assertOk();
    $response->assertHeader('X-Correlation-ID', 'deployment-health');
    $response->assertJsonPath('status', 'degraded');
    $response->assertJsonPath('report.issues.0', 'min_greater_than_max');
});

it('E11-F2-I2 #429 allows admins to update deployments while preserving correlation ids', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    provisionRoles($tenant);

    /** @var HorizonDeployment $deployment */
    $deployment = HorizonDeployment::factory()->forBrand($brand)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'update-horizon',
        'domain' => 'old.example.test',
    ]);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);
    horizonVerifyTwoFactor($admin, $tenant, $brand);

    $response = patchJson(
        '/api/v1/horizon-deployments/'.$deployment->getKey(),
        [
            'domain' => 'new.example.test',
            'uses_tls' => true,
        ],
        array_merge(horizonHeaders($tenant, $brand), ['X-Correlation-ID' => 'update-correlation'])
    );

    $response->assertOk();
    $response->assertJsonPath('meta.correlation_id', 'update-correlation');
    $response->assertJsonPath('data.attributes.domain', 'new.example.test');

    $deployment->refresh();
    expect($deployment->uses_tls)->toBeTrue();
    expect($deployment->domain)->toBe('new.example.test');
});
