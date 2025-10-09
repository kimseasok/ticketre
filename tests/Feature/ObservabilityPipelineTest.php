<?php

use App\Models\Brand;
use App\Models\ObservabilityPipeline;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\ObservabilityMetricRecorder;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
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

    app(ObservabilityMetricRecorder::class)->clear();
});

function observabilityPipelineHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function observabilityVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E11-F4-I2 #435 allows admins to create observability pipelines via API', function () {
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
    observabilityVerifyTwoFactor($admin);

    $payload = [
        'name' => 'Central Logging',
        'slug' => 'central-logging',
        'pipeline_type' => 'logs',
        'ingest_endpoint' => 'https://logs.internal.example/v1/ingest',
        'buffer_strategy' => 'disk',
        'buffer_retention_seconds' => 900,
        'retry_backoff_seconds' => 30,
        'max_retry_attempts' => 5,
        'batch_max_bytes' => 1048576,
        'metadata' => [
            'description' => 'NON-PRODUCTION aggregated logging pipeline.',
        ],
    ];

    $response = postJson('/api/v1/observability-pipelines', $payload, array_merge(observabilityPipelineHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.slug', 'central-logging');
    $response->assertJsonPath('data.attributes.ingest_endpoint_digest', hash('sha256', 'https://logs.internal.example/v1/ingest'));

    expect(ObservabilityPipeline::where('slug', 'central-logging')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E11-F4-I2 #435 prevents viewers from managing pipelines', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/observability-pipelines', [
        'name' => 'Unauthorized Pipeline',
        'pipeline_type' => 'logs',
        'ingest_endpoint' => 'https://logs.invalid/ingest',
        'buffer_retention_seconds' => 300,
        'retry_backoff_seconds' => 5,
        'max_retry_attempts' => 1,
        'batch_max_bytes' => 1024,
    ], observabilityPipelineHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F4-I2 #435 validates pipeline type options', function () {
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
    observabilityVerifyTwoFactor($admin);

    $response = postJson('/api/v1/observability-pipelines', [
        'name' => 'Invalid Pipeline',
        'pipeline_type' => 'invalid-type',
        'ingest_endpoint' => 'https://logs.invalid/ingest',
        'buffer_retention_seconds' => 300,
        'retry_backoff_seconds' => 5,
        'max_retry_attempts' => 1,
        'batch_max_bytes' => 1024,
    ], observabilityPipelineHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E11-F4-I2 #435 allows agents to list pipelines with tenant scope', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $pipeline = ObservabilityPipeline::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'agent-visible-pipeline',
        'pipeline_type' => 'logs',
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    observabilityVerifyTwoFactor($agent);

    $response = getJson('/api/v1/observability-pipelines', observabilityPipelineHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonFragment(['slug' => 'agent-visible-pipeline']);
    $response->assertJsonPath('data.0.relationships.tenant.data.attributes.slug', $tenant->slug);
});

it('E11-F4-I2 #435 enforces tenant isolation for pipelines', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $pipeline = ObservabilityPipeline::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'pipeline_type' => 'logs',
    ]);

    $admin = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $admin->assignRole('Admin');

    app()->instance('currentTenant', $otherTenant);
    app()->forgetInstance('currentBrand');

    actingAs($admin);

    $response = getJson('/api/v1/observability-pipelines/'.$pipeline->getKey(), observabilityPipelineHeaders($otherTenant));

    $response->assertStatus(404);
});

it('E11-F4-I2 #435 updates observability pipelines and hashes endpoints', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $pipeline = ObservabilityPipeline::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ingest_endpoint' => 'https://logs.example/ingest',
        'pipeline_type' => 'logs',
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    observabilityVerifyTwoFactor($admin);

    $response = patchJson('/api/v1/observability-pipelines/'.$pipeline->getKey(), [
        'ingest_endpoint' => 'https://logs.example/updated',
        'max_retry_attempts' => 9,
    ], observabilityPipelineHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.attributes.max_retry_attempts', 9);
    $response->assertJsonPath('data.attributes.ingest_endpoint_digest', hash('sha256', 'https://logs.example/updated'));
});

it('E11-F4-I2 #435 exposes Prometheus metrics with tenant labels', function () {
    $tenant = Tenant::factory()->create(['slug' => 'tenant-observability']);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'brand-observability']);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $pipeline = ObservabilityPipeline::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'pipeline_type' => 'metrics',
        'metrics_scrape_interval_seconds' => 60,
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    observabilityVerifyTwoFactor($admin);

    // Trigger an update to record metrics
    patchJson('/api/v1/observability-pipelines/'.$pipeline->getKey(), [
        'buffer_retention_seconds' => 1200,
    ], observabilityPipelineHeaders($tenant, $brand));

    $metricsResponse = get('/api/v1/observability-pipelines/metrics', array_merge(observabilityPipelineHeaders($tenant, $brand), [
        'Accept' => 'text/plain',
    ]));

    $metricsResponse->assertOk();
    expect($metricsResponse->headers->get('Content-Type'))->toContain('text/plain; version=0.0.4');

    $body = $metricsResponse->content();
    expect($body)->toContain('observability_pipeline_operations_total');
    expect($body)->toContain('tenant_id="'.$tenant->id.'"');
    expect($body)->toContain('pipeline_type="metrics"');
});

it('E11-F4-I2 #435 denies metrics export without observability permission', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $user->givePermissionTo('platform.access');

    actingAs($user);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $user->id,
    ]);
    observabilityVerifyTwoFactor($user);

    $response = get('/api/v1/observability-pipelines/metrics', array_merge(observabilityPipelineHeaders($tenant, $brand), [
        'Accept' => 'text/plain',
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F4-I2 #435 allows admins to delete observability pipelines', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $pipeline = ObservabilityPipeline::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'pipeline_type' => 'logs',
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    observabilityVerifyTwoFactor($admin);

    $response = deleteJson('/api/v1/observability-pipelines/'.$pipeline->getKey(), [], observabilityPipelineHeaders($tenant, $brand));

    $response->assertNoContent();
    expect(ObservabilityPipeline::withTrashed()->find($pipeline->getKey())->trashed())->toBeTrue();
});
