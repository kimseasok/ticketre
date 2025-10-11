<?php

use App\Models\Brand;
use App\Models\Contact;
use App\Models\PortalIdentity;
use App\Models\PortalSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function portalAuthHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function createTestPortalIdentity(Tenant $tenant, Brand $brand, Contact $contact, ?array $abilities = null): PortalIdentity
{
    return PortalIdentity::query()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'contact_id' => $contact->id,
        'provider' => 'password',
        'identifier' => $contact->email,
        'secret_hash' => Hash::make('PortalPass!123'),
        'abilities' => $abilities ?? [
            'portal.access',
            'portal.tickets.submit',
            'portal.tickets.view',
            'portal.session.read',
            'portal.session.terminate',
        ],
        'metadata' => ['notes' => 'NON-PRODUCTION test identity'],
    ]);
}

it('E9-F3-I2 issues tokens for valid portal credentials', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact);

    $response = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonPath('data.attributes.token_type', 'Bearer');
    $response->assertJsonStructure(['data' => ['attributes' => ['access_token', 'refresh_token']]]);

    /** @var PortalSession $session */
    $session = PortalSession::query()->firstOrFail();
    expect($session->tenant_id)->toBe($tenant->id);
    expect($session->contact_id)->toBe($contact->id);
});

it('E9-F3-I2 rejects invalid portal credentials', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact);

    $response = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'WrongPassword',
    ], portalAuthHeaders($tenant, $brand));

    $response->assertStatus(401);
    $response->assertJsonPath('error.code', 'ERR_INVALID_CREDENTIALS');
});

it('E9-F3-I2 refreshes tokens with a valid refresh token', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createPortalIdentity($tenant, $brand, $contact);

    $login = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand))->json('data.attributes');

    $refresh = postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login['refresh_token'],
    ], portalAuthHeaders($tenant, $brand));

    $refresh->assertOk();
    expect($refresh->json('data.attributes.refresh_token'))->not->toBe($login['refresh_token']);
    /** @var PortalSession $session */
    $session = PortalSession::query()->firstOrFail();
    expect($session->refresh_expires_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('E9-F3-I2 rejects refresh attempts once the session is expired', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact);

    $login = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand))->json('data.attributes');

    /** @var PortalSession $session */
    $session = PortalSession::query()->firstOrFail();
    $session->refresh_expires_at = now()->subMinute();
    $session->save();

    $response = postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login['refresh_token'],
    ], portalAuthHeaders($tenant, $brand));

    $response->assertStatus(401);
    $response->assertJsonPath('error.code', 'ERR_TOKEN_EXPIRED');
});

it('E9-F3-I2 enforces portal middleware abilities', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact, ['portal.access']);

    $login = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand))->json('data.attributes');

    $headers = array_merge(portalAuthHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$login['access_token'],
    ]);

    getJson('/api/v1/portal/session', $headers)->assertStatus(403);
    postJson('/api/v1/portal/auth/logout', ['refresh_token' => $login['refresh_token']], $headers)->assertStatus(403);
});

it('E9-F3-I2 revokes sessions and blocks reuse', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact);

    $login = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand))->json('data.attributes');

    $headers = array_merge(portalAuthHeaders($tenant, $brand), [
        'Authorization' => 'Bearer '.$login['access_token'],
    ]);

    postJson('/api/v1/portal/auth/logout', ['refresh_token' => $login['refresh_token']], $headers)
        ->assertOk();

    /** @var PortalSession $session */
    $session = PortalSession::query()->firstOrFail();
    expect($session->revoked_at)->not()->toBeNull();

    postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login['refresh_token'],
    ], portalAuthHeaders($tenant, $brand))->assertStatus(401);
});

it('E9-F3-I2 enforces policy matrix for portal session management', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var Contact $contact */
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    createTestPortalIdentity($tenant, $brand, $contact);
    postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contact->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenant, $brand));
    $session = PortalSession::query()->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    /** @var User $agent */
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    /** @var User $viewer */
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($admin)->getJson('/api/v1/portal-sessions', portalAuthHeaders($tenant, $brand))->assertOk();
    actingAs($agent)->getJson('/api/v1/portal-sessions', portalAuthHeaders($tenant, $brand))->assertOk();
    actingAs($viewer)->getJson('/api/v1/portal-sessions', portalAuthHeaders($tenant, $brand))->assertStatus(403);

    actingAs($agent)->deleteJson(
        sprintf('/api/v1/portal-sessions/%d', $session->getKey()),
        portalAuthHeaders($tenant, $brand)
    )->assertStatus(403);

    actingAs($admin)->deleteJson(
        sprintf('/api/v1/portal-sessions/%d', $session->getKey()),
        portalAuthHeaders($tenant, $brand)
    )->assertNoContent();
});

it('E9-F3-I2 prevents cross-tenant refresh attempts', function () {
    /** @var Tenant $tenantA */
    $tenantA = Tenant::factory()->create();
    /** @var Brand $brandA */
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    /** @var Contact $contactA */
    $contactA = Contact::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    createTestPortalIdentity($tenantA, $brandA, $contactA);

    $login = postJson('/api/v1/portal/auth/login', [
        'provider' => 'password',
        'identifier' => $contactA->email,
        'credential' => 'PortalPass!123',
    ], portalAuthHeaders($tenantA, $brandA))->json('data.attributes');

    /** @var Tenant $tenantB */
    $tenantB = Tenant::factory()->create();

    postJson('/api/v1/portal/auth/refresh', [
        'refresh_token' => $login['refresh_token'],
    ], portalAuthHeaders($tenantB))->assertStatus(403);
});
