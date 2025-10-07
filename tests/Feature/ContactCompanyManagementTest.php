<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function contactHeaders(Tenant $tenant): array
{
    return [
        'X-Tenant' => $tenant->slug,
        'Accept' => 'application/json',
    ];
}

it('E2-F1-I2 allows admins to create contacts with tags and consent', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    $company = Company::factory()->create(['tenant_id' => $tenant->id]);
    $tag = ContactTag::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($admin);

    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1555123456',
        'company_id' => $company->id,
        'gdpr_consent' => true,
        'gdpr_consent_method' => 'portal-form',
        'gdpr_consent_source' => 'web',
        'gdpr_notes' => 'Provided consent verbally during kickoff.',
        'tags' => [$tag->id],
    ];

    $response = postJson('/api/v1/contacts', $payload, contactHeaders($tenant));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Jane Doe');
    $response->assertJsonPath('data.gdpr.consent', true);
    $response->assertJsonPath('data.tags.0.id', $tag->id);

    $contact = Contact::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($contact->tags)->toHaveCount(1);
    expect(AuditLog::query()->where('auditable_type', Contact::class)->where('auditable_id', $contact->id)->where('action', 'contact.created')->exists())->toBeTrue();
});

it('E2-F1-I2 rejects duplicate emails per tenant', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    Contact::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'duplicate@example.com',
    ]);

    actingAs($admin);

    $response = postJson('/api/v1/contacts', [
        'name' => 'Dup User',
        'email' => 'duplicate@example.com',
        'gdpr_consent' => true,
        'gdpr_consent_method' => 'import',
    ], contactHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F1-I2 enforces policy matrix for viewers and agents', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $listResponse = getJson('/api/v1/contacts', contactHeaders($tenant));
    $listResponse->assertOk();

    $createResponse = postJson('/api/v1/contacts', [
        'name' => 'Unauthorized',
        'email' => 'viewer@example.com',
        'gdpr_consent' => true,
        'gdpr_consent_method' => 'manual',
    ], contactHeaders($tenant));

    $createResponse->assertForbidden();
    $createResponse->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F1-I2 prevents cross-tenant access to contacts', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $adminB->assignRole('Admin');

    app()->instance('currentTenant', $tenantB);
    $foreignContact = Contact::factory()->create([
        'tenant_id' => $tenantB->id,
        'email' => 'foreign@example.com',
    ]);

    actingAs($adminA);
    app()->instance('currentTenant', $tenantA);

    $response = getJson('/api/v1/contacts/'.$foreignContact->id, contactHeaders($tenantA));

    $response->assertNotFound();
});

it('E2-F1-I2 returns structured error payload on validation failure', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/contacts', [
        'email' => 'not-an-email',
        'gdpr_consent' => false,
    ], contactHeaders($tenant));

    $response->assertUnprocessable();
    $response->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});

it('E2-F1-I2 allows admins to manage companies', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $payload = [
        'name' => 'Example Corp',
        'domain' => 'example.test',
    ];

    $createResponse = postJson('/api/v1/companies', $payload, contactHeaders($tenant));
    $createResponse->assertCreated();

    $companyId = $createResponse->json('data.id');

    $update = patchJson('/api/v1/companies/'.$companyId, [
        'metadata' => ['segment' => 'Enterprise'],
    ], contactHeaders($tenant));

    $update->assertOk();
    $update->assertJsonPath('data.metadata.segment', 'Enterprise');

    expect(AuditLog::query()->where('auditable_type', Company::class)->where('auditable_id', $companyId)->where('action', 'company.updated')->exists())->toBeTrue();

    $delete = deleteJson('/api/v1/companies/'.$companyId, [], contactHeaders($tenant));
    $delete->assertNoContent();
});

it('E2-F1-I2 forbids viewers from mutating companies', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/companies', [
        'name' => 'Unauthorized Inc',
    ], contactHeaders($tenant));

    $response->assertForbidden();
});
