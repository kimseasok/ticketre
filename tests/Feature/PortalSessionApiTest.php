<?php

namespace Egulias\EmailValidator\Validation {
    if (! function_exists(__NAMESPACE__.'\\dns_get_record')) {
        function dns_get_record(string $hostname, int $type = DNS_MX): array
        {
            return [['host' => $hostname, 'type' => 'MX', 'target' => 'mail.'.$hostname]];
        }
    }
}

namespace {

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\PortalAccount;
use App\Models\Contact;
use App\Models\Company;
use App\Models\PortalSession;
use App\Models\Role;
use App\Models\TwoFactorCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Permission;
use App\Services\PortalSessionService;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }
});

function portalSessionHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function createPortalAccount(Tenant $tenant, Brand $brand, array $overrides = []): PortalAccount
{
    $company = Company::query()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'name' => 'Portal Company '.Str::uuid(),
        'domain' => 'portal.example',
        'metadata' => [],
        'tags' => [],
    ]);

    $contact = Contact::query()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'company_id' => $company->id,
        'name' => 'Portal Contact '.Str::uuid(),
        'email' => Str::uuid().'@example.com',
        'phone' => null,
        'metadata' => [],
        'tags' => [],
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ]);

    return PortalAccount::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'contact_id' => $contact->id,
        'email' => 'portal-'.Str::uuid().'@gmail.com',
        'password' => Hash::make('PortalPass123!'),
        'status' => PortalAccount::STATUS_ACTIVE,
        'metadata' => [],
        'last_login_at' => null,
    ], $overrides));
}

function seedPortalPermissions(Tenant $tenant): void
{
    app()->instance('currentTenant', $tenant);
    Permission::firstOrCreate([
        'name' => 'portal.access',
        'guard_name' => 'portal',
    ], [
        'description' => null,
        'is_system' => true,
    ]);

    Permission::firstOrCreate([
        'name' => 'portal.tickets.view',
        'guard_name' => 'portal',
    ], [
        'description' => null,
        'is_system' => true,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->forgetInstance('currentTenant');
}

function grantPortalPermissions(PortalAccount $account, array $permissions): void
{
    $account->loadMissing('tenant', 'brand');

    $tenant = $account->tenant;
    $brand = $account->brand;

    if ($tenant) {
        app()->instance('currentTenant', $tenant);
    }

    if ($brand) {
        app()->instance('currentBrand', $brand);
    }

    $account->syncPermissions($permissions);

    if ($tenant) {
        app()->forgetInstance('currentTenant');
    }

    if ($brand) {
        app()->forgetInstance('currentBrand');
    }
}

function assignTenantRole(User $user, string $role): void
{
    $user->loadMissing('tenant', 'brand');

    $tenant = $user->tenant;
    $brand = $user->brand;

    if ($tenant) {
        app()->instance('currentTenant', $tenant);
    }

    if ($brand) {
        app()->instance('currentBrand', $brand);
    }

    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if ($tenant) {
        app()->forgetInstance('currentTenant');
    }

    if ($brand) {
        app()->forgetInstance('currentBrand');
    }
}

it('E9-F3-I2 issues JWT tokens and persists session', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand, [
        'email' => 'portal.user@gmail.com',
        'password' => Hash::make('Secret!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $response = postJson('/api/v1/portal/auth/login', [
        'email' => 'portal.user@gmail.com',
        'password' => 'Secret!234',
        'device_name' => 'Test Device',
    ], portalSessionHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.type', 'portal_sessions');
    $response->assertJsonStructure(['data' => ['attributes' => ['access_token', 'refresh_token', 'expires_at']]]);

    $session = PortalSession::query()->with('account')->first();
    expect($session)->not->toBeNull();
    expect($session->abilities)->toContain('portal.access');

    expect(AuditLog::query()->where('action', 'portal_session.issued')->exists())->toBeTrue();
});

it('E9-F3-I2 rejects invalid portal credentials', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $response = postJson('/api/v1/portal/auth/login', [
        'email' => $account->email,
        'password' => 'wrong-password',
    ], portalSessionHeaders($tenant, $brand));

    $response->assertStatus(401);
    $response->assertJsonPath('error.code', 'ERR_UNAUTHENTICATED');
});

it('E9-F3-I2 refreshes JWT tokens and rotates refresh token', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Refresh!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $login = postJson('/api/v1/portal/auth/login', [
        'email' => $account->email,
        'password' => 'Refresh!234',
    ], portalSessionHeaders($tenant, $brand));

    $login->assertOk();
    $session = PortalSession::query()->firstOrFail();
    $originalHash = $session->refresh_token_hash;

    $refresh = postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login->json('data.attributes.refresh_token'),
        'device_name' => 'Rotated Device',
    ], portalSessionHeaders($tenant, $brand));

    $refresh->assertOk();
    $session->refresh();
    expect($session->refresh_token_hash)->not->toBe($originalHash);
    expect($refresh->json('data.attributes.access_token'))->not->toBe($login->json('data.attributes.access_token'));
});

it('E9-F3-I2 prevents refresh with expired token', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Expire!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $login = postJson('/api/v1/portal/auth/login', [
        'email' => $account->email,
        'password' => 'Expire!234',
    ], portalSessionHeaders($tenant, $brand));

    $login->assertOk();
    $session = PortalSession::query()->firstOrFail();
    $session->update(['refresh_expires_at' => now()->subMinute()]);

    $refresh = postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login->json('data.attributes.refresh_token'),
    ], portalSessionHeaders($tenant, $brand));

    $refresh->assertStatus(401);
    $refresh->assertJsonPath('error.code', 'ERR_UNAUTHENTICATED');
});

it('E9-F3-I2 revokes sessions on logout', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Logout!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $login = postJson('/api/v1/portal/auth/login', [
        'email' => $account->email,
        'password' => 'Logout!234',
    ], portalSessionHeaders($tenant, $brand));

    $token = $login->json('data.attributes.access_token');
    $refresh = $login->json('data.attributes.refresh_token');

    $logout = postJson('/api/v1/portal/auth/logout', [], array_merge(portalSessionHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$token,
    ]));

    $logout->assertNoContent();

    $session = PortalSession::query()->firstOrFail();
    expect($session->revoked_at)->not->toBeNull();

    $sessionCheck = getJson('/api/v1/portal/auth/session', array_merge(portalSessionHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$token,
    ]));
    $sessionCheck->assertStatus(401);

    $refreshAttempt = postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $refresh,
    ], portalSessionHeaders($tenant, $brand));
    $refreshAttempt->assertStatus(401);
});

it('E9-F3-I2 enforces portal ability middleware', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $restrictedAccount = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Access!234'),
    ]);
    grantPortalPermissions($restrictedAccount, ['portal.access']);

    $restrictedLogin = postJson('/api/v1/portal/auth/login', [
        'email' => $restrictedAccount->email,
        'password' => 'Access!234',
    ], portalSessionHeaders($tenant, $brand));

    $restrictedLogin->assertOk();
    $restrictedToken = $restrictedLogin->json('data.attributes.access_token');

    $abilitiesResponse = getJson('/api/v1/portal/auth/abilities', array_merge(portalSessionHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$restrictedToken,
    ]));
    $abilitiesResponse->assertStatus(403);

    $fullAccount = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('View!234'),
    ]);
    grantPortalPermissions($fullAccount, ['portal.access', 'portal.tickets.view']);

    $fullLogin = postJson('/api/v1/portal/auth/login', [
        'email' => $fullAccount->email,
        'password' => 'View!234',
    ], portalSessionHeaders($tenant, $brand));

    $fullToken = $fullLogin->json('data.attributes.access_token');
    $abilitiesOk = getJson('/api/v1/portal/auth/abilities', array_merge(portalSessionHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$fullToken,
    ]));
    $abilitiesOk->assertOk();
    $abilitiesOk->assertJsonPath('data.abilities.0', 'portal.access');
});

it('E9-F3-I2 allows admin to manage portal sessions with RBAC enforcement', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'Admin')->firstOrFail();
    $adminRole->loadMissing('permissions');
    expect($adminRole->permissions->pluck('name'))->toContain('portal.sessions.view');
    expect($adminRole->permissions->pluck('name'))->toContain('portal.sessions.manage');

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    assignTenantRole($admin, 'Admin');
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    assignTenantRole($agent, 'Agent');
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    assignTenantRole($viewer, 'Viewer');

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    session()->put('two_factor_verified_'.$admin->id, now()->addMinutes(10)->toIso8601String());
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    session()->put('two_factor_verified_'.$agent->id, now()->addMinutes(10)->toIso8601String());

    app()->instance('currentTenant', $tenant);
    expect($admin->getAllPermissions()->pluck('name'))->toContain('portal.sessions.view');
    expect($admin->getAllPermissions()->pluck('name'))->toContain('portal.sessions.manage');
    expect($admin->can('portal.sessions.view'))->toBeTrue();
    expect($admin->can('portal.sessions.manage'))->toBeTrue();
    app()->forgetInstance('currentTenant');

    $account = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Manage!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    $tokens = app(PortalSessionService::class)->issueForAccount(
        $account,
        '203.0.113.5',
        'AdminTest/1.0',
        'admin-session-test'
    );
    app()->forgetInstance('currentTenant');
    app()->forgetInstance('currentBrand');

    $session = $tokens->session;

    actingAs($admin);
    $indexResponse = getJson('/api/v1/portal-sessions', portalSessionHeaders($tenant, $brand));
    $indexResponse->assertOk();
    $showResponse = getJson('/api/v1/portal-sessions/'.$session->getKey(), portalSessionHeaders($tenant, $brand));
    $showResponse->assertOk();
    deleteJson('/api/v1/portal-sessions/'.$session->getKey(), [], portalSessionHeaders($tenant, $brand))->assertNoContent();
    $session->refresh();
    expect($session->revoked_at)->not->toBeNull();

    actingAs($agent);
    getJson('/api/v1/portal-sessions', portalSessionHeaders($tenant, $brand))->assertOk();
    deleteJson('/api/v1/portal-sessions/'.$session->getKey(), [], portalSessionHeaders($tenant, $brand))->assertStatus(403);

    actingAs($viewer);
    getJson('/api/v1/portal-sessions', portalSessionHeaders($tenant, $brand))->assertStatus(403);
});

it('E9-F3-I2 prevents cross-tenant portal session access', function () {
    $tenantA = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    seedPortalPermissions($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    assignTenantRole($adminA, 'Admin');
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
        'user_id' => $adminA->id,
    ]);
    session()->put('two_factor_verified_'.$adminA->id, now()->addMinutes(10)->toIso8601String());

    $accountA = createPortalAccount($tenantA, $brandA);
    grantPortalPermissions($accountA, ['portal.access', 'portal.tickets.view']);

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);
    $sessionA = app(PortalSessionService::class)->issueForAccount(
        $accountA,
        '192.0.2.5',
        'TenantA/1.0',
        'tenant-a-session'
    )->session;
    app()->forgetInstance('currentTenant');
    app()->forgetInstance('currentBrand');

    $tenantB = Tenant::factory()->create();
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);
    seedPortalPermissions($tenantB);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);
    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    assignTenantRole($adminB, 'Admin');
    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
        'user_id' => $adminB->id,
    ]);
    session()->put('two_factor_verified_'.$adminB->id, now()->addMinutes(10)->toIso8601String());

    actingAs($adminB);
    getJson('/api/v1/portal-sessions/'.$sessionA->getKey(), portalSessionHeaders($tenantB, $brandB))->assertStatus(404);
});

it('E9-F3-I2 exposes session details for authenticated portal users', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    seedPortalPermissions($tenant);

    $account = createPortalAccount($tenant, $brand, [
        'password' => Hash::make('Session!234'),
    ]);
    grantPortalPermissions($account, ['portal.access', 'portal.tickets.view']);

    $login = postJson('/api/v1/portal/auth/login', [
        'email' => $account->email,
        'password' => 'Session!234',
    ], portalSessionHeaders($tenant, $brand));

    $token = $login->json('data.attributes.access_token');

    $sessionResponse = getJson('/api/v1/portal/auth/session', array_merge(portalSessionHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$token,
    ]));

    $sessionResponse->assertOk();
    $sessionResponse->assertJsonPath('data.attributes.portal_account_id', PortalSession::query()->firstOrFail()->portal_account_id);
});

}
