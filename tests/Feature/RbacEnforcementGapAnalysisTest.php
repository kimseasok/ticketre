<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\RbacEnforcementGapAnalysis;
use App\Models\Tenant;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\RbacEnforcementGapAnalysisService;
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

function rbacHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function rbacVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E10-F2-I1 #422 allows admins to create RBAC gap analyses via API', function () {
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
    rbacVerifyTwoFactor($admin);

    $payload = [
        'title' => 'Quarterly RBAC Gap Assessment',
        'status' => 'in_progress',
        'analysis_date' => now()->toIso8601String(),
        'audit_matrix' => [
            [
                'type' => 'route',
                'identifier' => 'GET /api/v1/tickets',
                'required_permissions' => ['tickets.view'],
                'roles' => ['Admin'],
                'notes' => 'NON-PRODUCTION scenario',
            ],
        ],
        'findings' => [
            [
                'priority' => 'high',
                'summary' => 'Portal submission missing middleware',
                'owner' => 'Security Engineering',
                'eta_days' => 7,
                'status' => 'open',
            ],
        ],
        'remediation_plan' => [
            'milestone_one' => 'Deploy RBAC middleware',
        ],
        'review_minutes' => 'NON-PRODUCTION recap of RBAC review meeting.',
        'notes' => 'NON-PRODUCTION data only.',
        'owner_team' => 'Trust & Safety',
    ];

    $correlation = Str::uuid()->toString();

    $response = postJson('/api/v1/rbac-gap-analyses', $payload, array_merge(rbacHeaders($tenant, $brand), [
        'X-Correlation-ID' => $correlation,
    ]));

    $response->assertCreated();
    $response->assertHeader('X-Correlation-ID', $correlation);
    $response->assertJsonPath('data.type', 'rbac-gap-analyses');
    $response->assertJsonPath('data.attributes.status', 'in_progress');

    expect(RbacEnforcementGapAnalysis::where('tenant_id', $tenant->id)->where('status', 'in_progress')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'rbac_gap_analysis.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E10-F2-I1 #422 prevents viewers from managing RBAC gap analyses', function () {
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
    rbacVerifyTwoFactor($viewer);

    $response = postJson('/api/v1/rbac-gap-analyses', [
        'title' => 'Another Test',
        'status' => 'draft',
        'analysis_date' => now()->toIso8601String(),
        'audit_matrix' => [
            [
                'type' => 'route',
                'identifier' => 'GET /api/v1/demo',
                'required_permissions' => ['tickets.view'],
                'roles' => ['Viewer'],
            ],
        ],
        'findings' => [
            [
                'priority' => 'low',
                'summary' => 'None',
            ],
        ],
        'review_minutes' => 'n/a',
    ], rbacHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E10-F2-I1 #422 validates incoming RBAC payloads', function () {
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
    rbacVerifyTwoFactor($admin);

    $response = postJson('/api/v1/rbac-gap-analyses', [
        'title' => '',
        'status' => 'unsupported',
        'analysis_date' => 'invalid-date',
        'audit_matrix' => 'not-an-array',
        'findings' => 'not-an-array',
        'review_minutes' => 123,
    ], rbacHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E10-F2-I1 #422 enforces tenant isolation for RBAC analyses', function () {
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
    rbacVerifyTwoFactor($adminB);

    $service = app(RbacEnforcementGapAnalysisService::class);
    $service->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'title' => 'Tenant B Analysis',
        'status' => 'draft',
        'analysis_date' => now()->toIso8601String(),
        'audit_matrix' => [
            [
                'type' => 'route',
                'identifier' => 'GET /api/v1/b',
                'required_permissions' => ['tickets.view'],
                'roles' => ['Admin'],
            ],
        ],
        'findings' => [
            [
                'priority' => 'medium',
                'summary' => 'Check queue access',
            ],
        ],
        'review_minutes' => 'NON-PRODUCTION minutes',
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
    rbacVerifyTwoFactor($adminA);

    $response = getJson('/api/v1/rbac-gap-analyses', rbacHeaders($tenantA, $brandA));

    $response->assertOk();
    $response->assertJsonMissing(['title' => 'Tenant B Analysis']);
});

it('E10-F2-I1 #422 enforces policy expectations for admin, agent, and viewer roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);

    rbacVerifyTwoFactor($admin);

    actingAs($admin);
    $service = app(RbacEnforcementGapAnalysisService::class);
    $analysis = $service->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'title' => 'Policy Matrix Analysis',
        'status' => 'draft',
        'analysis_date' => now()->toIso8601String(),
        'audit_matrix' => [
            [
                'type' => 'command',
                'identifier' => 'queue:work',
                'required_permissions' => ['tickets.manage'],
                'roles' => ['Admin'],
            ],
        ],
        'findings' => [
            [
                'priority' => 'low',
                'summary' => 'Baseline review',
            ],
        ],
        'review_minutes' => 'NON-PRODUCTION minutes',
    ], $admin);

    $headers = rbacHeaders($tenant, $brand);

    actingAs($agent);
    rbacVerifyTwoFactor($agent);
    getJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), $headers)->assertOk();
    patchJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), [
        'status' => 'completed',
        'findings' => [
            [
                'priority' => 'low',
                'summary' => 'Baseline review',
            ],
        ],
        'audit_matrix' => [
            [
                'type' => 'command',
                'identifier' => 'queue:work',
                'required_permissions' => ['tickets.manage'],
                'roles' => ['Admin'],
            ],
        ],
        'review_minutes' => 'Updated minutes',
    ], $headers)->assertStatus(403);

    actingAs($viewer);
    rbacVerifyTwoFactor($viewer);
    getJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), $headers)->assertOk();
    patchJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), [
        'status' => 'in_progress',
        'findings' => [
            [
                'priority' => 'low',
                'summary' => 'Baseline review',
            ],
        ],
        'audit_matrix' => [
            [
                'type' => 'command',
                'identifier' => 'queue:work',
                'required_permissions' => ['tickets.manage'],
                'roles' => ['Admin'],
            ],
        ],
        'review_minutes' => 'Viewer attempt update',
    ], $headers)->assertStatus(403);

    actingAs($admin);
    rbacVerifyTwoFactor($admin);
    patchJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), [
        'status' => 'completed',
        'findings' => [
            [
                'priority' => 'low',
                'summary' => 'Baseline review',
            ],
        ],
        'audit_matrix' => [
            [
                'type' => 'command',
                'identifier' => 'queue:work',
                'required_permissions' => ['tickets.manage'],
                'roles' => ['Admin'],
            ],
        ],
        'review_minutes' => 'Admin updated minutes',
    ], $headers)->assertOk();
    deleteJson('/api/v1/rbac-gap-analyses/'.$analysis->getKey(), [], $headers)->assertNoContent();
});
