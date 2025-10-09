<?php

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OTPHP\TOTP;
use Symfony\Component\HttpFoundation\Response;

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

    Carbon::setTestNow(null);
});

function twoFactorHeaders(Tenant $tenant, ?Brand $brand = null): array
{
    $headers = [
        'X-Tenant' => $tenant->slug,
        'Accept' => 'application/json',
        'X-Correlation-ID' => (string) Str::uuid(),
    ];

    if ($brand) {
        $headers['X-Brand'] = $brand->slug;
    }

    return $headers;
}

it('E10-F1-I2 requires admins to complete two-factor challenge before accessing protected APIs', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $listResponse = getJson('/api/v1/teams', twoFactorHeaders($tenant, $brand));
    $listResponse->assertStatus(Response::HTTP_FORBIDDEN);
    $listResponse->assertJsonPath('error.code', 'ERR_2FA_NOT_CONFIRMED');

    $enroll = postJson('/api/v1/two-factor/enroll', ['label' => 'Primary Device'], twoFactorHeaders($tenant, $brand));
    $enroll->assertCreated();
    $secret = $enroll->json('meta.secret');

    $totp = TOTP::create($secret, 30, 'sha1', 6);
    $totp->setLabel($admin->email);
    $totp->setIssuer(config('app.name'));

    $code = $totp->now();

    $confirm = postJson('/api/v1/two-factor/confirm', ['code' => $code], twoFactorHeaders($tenant, $brand));
    $confirm->assertOk();
    $confirm->assertJsonPath('meta.recovery_codes.0', fn ($value) => is_string($value));

    $blocked = getJson('/api/v1/teams', twoFactorHeaders($tenant, $brand));
    $blocked->assertStatus(Response::HTTP_PRECONDITION_REQUIRED);
    $blocked->assertJsonPath('error.code', 'ERR_2FA_REQUIRED');

    $challenge = postJson('/api/v1/two-factor/challenge', ['code' => $totp->now()], twoFactorHeaders($tenant, $brand));
    $challenge->assertOk();
    $challenge->assertJsonPath('meta.verified_at', fn ($value) => ! empty($value));

    $allowed = getJson('/api/v1/teams', twoFactorHeaders($tenant, $brand));
    $allowed->assertStatus(Response::HTTP_OK);
});

it('E10-F1-I2 allows recovery code usage exactly once', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $enroll = postJson('/api/v1/two-factor/enroll', [], twoFactorHeaders($tenant, $brand));
    $secret = $enroll->json('meta.secret');

    $totp = TOTP::create($secret, 30, 'sha1', 6);
    $totp->setLabel($agent->email);
    $totp->setIssuer(config('app.name'));

    $confirm = postJson('/api/v1/two-factor/confirm', ['code' => $totp->now()], twoFactorHeaders($tenant, $brand));
    $confirm->assertOk();

    $recoveryCode = $confirm->json('meta.recovery_codes.0');

    $challenge = postJson('/api/v1/two-factor/challenge', ['recovery_code' => $recoveryCode], twoFactorHeaders($tenant, $brand));
    $challenge->assertOk();

    $reuse = postJson('/api/v1/two-factor/challenge', ['recovery_code' => $recoveryCode], twoFactorHeaders($tenant, $brand));
    $reuse->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $reuse->assertJsonPath('error.code', 'ERR_2FA_INVALID_CODE');
});

it('E10-F1-I2 locks credentials after repeated failures', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $enroll = postJson('/api/v1/two-factor/enroll', [], twoFactorHeaders($tenant, $brand));
    $secret = $enroll->json('meta.secret');

    $totp = TOTP::create($secret, 30, 'sha1', 6);
    $totp->setLabel($admin->email);
    $totp->setIssuer(config('app.name'));

    postJson('/api/v1/two-factor/confirm', ['code' => $totp->now()], twoFactorHeaders($tenant, $brand))->assertOk();

    foreach (range(1, config('security.two_factor.max_attempts')) as $_) {
        $response = postJson('/api/v1/two-factor/challenge', ['code' => '000000'], twoFactorHeaders($tenant, $brand));
    }

    $response->assertStatus(Response::HTTP_LOCKED);
    $response->assertJsonPath('error.code', 'ERR_2FA_LOCKED');
});

it('E10-F1-I2 restricts two-factor management to authorized roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $enroll = postJson('/api/v1/two-factor/enroll', [], twoFactorHeaders($tenant, $brand));
    $enroll->assertStatus(Response::HTTP_FORBIDDEN);
    $enroll->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E10-F1-I2 enforces tenant isolation for two-factor credentials', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    $adminB->assignRole('Admin');

    actingAs($adminB);

    $response = getJson('/api/v1/two-factor', twoFactorHeaders($tenantA, $brandA));
    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});
