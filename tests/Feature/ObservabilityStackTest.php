<?php

use App\Models\Brand;
use App\Models\ObservabilityStack;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\ObservabilityMetricRecorder;
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

    app(ObservabilityMetricRecorder::class)->clear();
});

function observabilityStackHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function observabilityStackVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E11-F4-I1 #434 allows admins to create observability stacks via API', function () {
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
    observabilityStackVerifyTwoFactor($admin);

    $payload = [
        'name' => 'Platform Observability Stack',
        'slug' => 'platform-observability-stack',
        'status' => 'selected',
        'logs_tool' => 'loki-grafana',
        'metrics_tool' => 'prometheus',
        'alerts_tool' => 'grafana-alerting',
        'log_retention_days' => 30,
        'metric_retention_days' => 30,
        'trace_retention_days' => 14,
        'estimated_monthly_cost' => 750.25,
        'trace_sampling_strategy' => 'probabilistic 15%',
        'decision_matrix' => [
            [
                'option' => 'ELK',
                'monthly_cost' => 1250,
                'scalability' => 'Requires dedicated Elasticsearch clusters.',
                'notes' => 'High cost but deep analytics.',
            ],
            [
                'option' => 'Loki/Grafana',
                'monthly_cost' => 750.25,
                'scalability' => 'Object storage backed and horizontally scalable.',
                'notes' => 'Selected for demo workloads.',
            ],
        ],
        'security_notes' => 'NON-PRODUCTION risk assessment complete.',
        'compliance_notes' => 'Retention meets GDPR Article 5.',
        'metadata' => [
            'owner' => 'platform',
        ],
    ];

    $response = postJson('/api/v1/observability-stacks', $payload, array_merge(observabilityStackHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.status', 'selected');
    $response->assertJsonPath('data.attributes.logs_tool', 'loki-grafana');
    $response->assertJsonPath('data.attributes.name_digest', hash('sha256', 'Platform Observability Stack'));

    expect(ObservabilityStack::where('slug', 'platform-observability-stack')->where('tenant_id', $tenant->id)->exists())
        ->toBeTrue();
});

it('E11-F4-I1 #434 prevents viewers from creating stacks', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/observability-stacks', [
        'name' => 'Unauthorized Stack',
        'status' => 'evaluating',
        'logs_tool' => 'elk',
        'metrics_tool' => 'prometheus',
        'alerts_tool' => 'grafana-alerting',
        'log_retention_days' => 7,
        'metric_retention_days' => 7,
    ], observabilityStackHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E11-F4-I1 #434 validates stack tool selections', function () {
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
    observabilityStackVerifyTwoFactor($admin);

    $response = postJson('/api/v1/observability-stacks', [
        'name' => 'Invalid Stack',
        'status' => 'unknown',
        'logs_tool' => 'invalid',
        'metrics_tool' => 'invalid',
        'alerts_tool' => 'invalid',
        'log_retention_days' => 0,
        'metric_retention_days' => 0,
    ], observabilityStackHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E11-F4-I1 #434 allows agents to list stacks scoped to their tenant', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    ObservabilityStack::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'tenant-visible-stack',
        'status' => 'evaluating',
    ]);

    ObservabilityStack::factory()->create([
        'tenant_id' => $otherTenant->id,
        'slug' => 'other-tenant-stack',
        'status' => 'selected',
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    observabilityStackVerifyTwoFactor($agent);

    $response = getJson('/api/v1/observability-stacks', observabilityStackHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonFragment(['slug' => 'tenant-visible-stack']);
    $response->assertJsonMissing(['slug' => 'other-tenant-stack']);
});

it('E11-F4-I1 #434 enforces update policy matrix', function (string $role, int $expectedStatus) {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $stack = ObservabilityStack::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'status' => 'evaluating',
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $user->assignRole($role);

    actingAs($user);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $user->id,
    ]);
    observabilityStackVerifyTwoFactor($user);

    $response = patchJson(
        '/api/v1/observability-stacks/'.$stack->getKey(),
        ['status' => 'selected'],
        observabilityStackHeaders($tenant, $brand)
    );

    $response->assertStatus($expectedStatus);

    if ($expectedStatus === 200) {
        $response->assertJsonPath('data.attributes.status', 'selected');
    }
})->with([
    'admin can update' => ['role' => 'Admin', 'expectedStatus' => 200],
    'agent cannot update' => ['role' => 'Agent', 'expectedStatus' => 403],
    'viewer cannot update' => ['role' => 'Viewer', 'expectedStatus' => 403],
]);

it('E11-F4-I1 #434 enforces delete authorization', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $stack = ObservabilityStack::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);
    observabilityStackVerifyTwoFactor($viewer);

    $response = deleteJson('/api/v1/observability-stacks/'.$stack->getKey(), [], observabilityStackHeaders($tenant, $brand));
    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});
