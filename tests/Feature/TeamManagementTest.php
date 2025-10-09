<?php

use App\Models\Brand;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
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

function teamHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F4-I1 allows admins to create teams via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'Escalation Team',
        'slug' => 'escalation-team',
        'default_queue' => 'vip',
        'description' => 'NON-PRODUCTION escalation pod.',
    ];

    $response = postJson('/api/v1/teams', $payload, array_merge(teamHeaders($tenant, $brand), [
        'X-Correlation-ID' => Str::uuid()->toString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.slug', 'escalation-team');
    $response->assertJsonPath('data.attributes.default_queue', 'vip');

    expect(Team::where('slug', 'escalation-team')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('E2-F4-I1 prevents agents from creating teams', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = postJson('/api/v1/teams', [
        'name' => 'Unauthorized Team',
    ], teamHeaders($tenant, $brand));

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F4-I1 validates unique team slugs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $existing = Team::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'support-team',
    ]);

    actingAs($admin);

    $response = postJson('/api/v1/teams', [
        'name' => 'Support Team',
        'slug' => 'support-team',
    ], teamHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F4-I1 allows admins to manage team memberships', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $member = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $member->assignRole('Agent');

    $team = Team::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'slug' => 'ops-team',
    ]);

    actingAs($admin);

    $response = postJson(
        "/api/v1/teams/{$team->getKey()}/memberships",
        [
            'user_id' => $member->getKey(),
            'role' => TeamMembership::ROLE_MEMBER,
            'is_primary' => true,
        ],
        teamHeaders($tenant, $brand)
    );

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.user_id', $member->getKey());

    $membership = TeamMembership::where('team_id', $team->id)->where('user_id', $member->id)->first();
    expect($membership)->not()->toBeNull();
    expect($membership->is_primary)->toBeTrue();
});

it('E2-F4-I1 prevents viewers from updating memberships', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    $member = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $member->assignRole('Agent');

    $team = Team::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $membership = TeamMembership::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamMembership::ROLE_MEMBER,
    ]);

    actingAs($viewer);

    $response = patchJson(
        "/api/v1/teams/{$team->getKey()}/memberships/{$membership->getKey()}",
        ['role' => TeamMembership::ROLE_LEAD],
        teamHeaders($tenant, $brand)
    );

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F4-I1 enforces tenant isolation for teams', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $foreignTeam = Team::factory()->create(['tenant_id' => $otherTenant->id, 'slug' => 'foreign-team']);

    actingAs($admin);

    $response = getJson("/api/v1/teams/{$foreignTeam->getKey()}", teamHeaders($tenant, $brand));

    $response->assertStatus(404);
});

it('E2-F4-I1 returns error schema for invalid membership payload', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $team = Team::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($admin);

    $response = postJson(
        "/api/v1/teams/{$team->getKey()}/memberships",
        [
            'user_id' => 9999,
            'role' => 'invalid-role',
        ],
        teamHeaders($tenant, $brand)
    );

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});
