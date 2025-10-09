<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function crmHeaders(Tenant $tenant, ?Brand $brand = null, ?string $correlationId = null): array
{
    $headers = [
        'X-Tenant' => $tenant->slug,
        'Accept' => 'application/json',
    ];

    if ($brand) {
        $headers['X-Brand'] = $brand->slug;
    }

    if ($correlationId) {
        $headers['X-Correlation-ID'] = $correlationId;
    }

    return $headers;
}

it('E2-F1-I2 allows admins to manage companies via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);
    $correlation = (string) Str::uuid();

    $response = postJson('/api/v1/companies', [
        'name' => 'Acme Industries',
        'domain' => 'acme.test',
        'tags' => ['vip', 'demo'],
    ], crmHeaders($tenant, $brand, $correlation));

    $response->assertCreated();
    $companyId = $response->json('data.id');
    expect($companyId)->not->toBeNull();

    $audit = AuditLog::query()
        ->where('auditable_type', \App\Models\Company::class)
        ->where('auditable_id', $companyId)
        ->where('action', 'company.created')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->changes['correlation_id'] ?? null)->toEqual($correlation);

    $updateResponse = patchJson("/api/v1/companies/{$companyId}", [
        'tags' => ['vip', 'renewal'],
    ], crmHeaders($tenant, $brand));

    $updateResponse->assertOk();
    $updateResponse->assertJsonPath('data.attributes.tags', ['vip', 'renewal']);
});

it('E2-F1-I2 prevents duplicate contact emails within a tenant', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Duplicate Test Co',
    ], crmHeaders($tenant, $brand));
    $companyResponse->assertCreated();
    $companyId = $companyResponse->json('data.id');

    $createResponse = postJson('/api/v1/contacts', [
        'name' => 'First Contact',
        'email' => 'dup@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brand));
    $createResponse->assertCreated();

    $duplicate = postJson('/api/v1/contacts', [
        'name' => 'Duplicate Contact',
        'email' => 'dup@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brand));

    $duplicate->assertUnprocessable();
    $duplicate->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F1-I2 enforces policy matrix for contacts and companies', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Policy Matrix Co',
    ], crmHeaders($tenant, $brand));
    $companyResponse->assertCreated();
    $companyId = $companyResponse->json('data.id');

    $contactResponse = postJson('/api/v1/contacts', [
        'name' => 'Policy Matrix Contact',
        'email' => 'policy@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brand));
    $contactResponse->assertCreated();

    actingAs($agent);
    getJson('/api/v1/companies', crmHeaders($tenant, $brand))->assertOk();
    getJson('/api/v1/contacts', crmHeaders($tenant, $brand))->assertOk();

    actingAs($viewer);
    getJson('/api/v1/companies', crmHeaders($tenant, $brand))->assertForbidden();
    getJson('/api/v1/contacts', crmHeaders($tenant, $brand))->assertForbidden();
});

it('E2-F1-I2 enforces tenant and brand isolation for contacts', function () {
    $tenantA = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $tenantB = Tenant::factory()->create();
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $adminB->assignRole('Admin');

    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);
    actingAs($adminB);

    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Isolation Co',
    ], crmHeaders($tenantB, $brandB));
    $companyId = $companyResponse->json('data.id');

    $contactResponse = postJson('/api/v1/contacts', [
        'name' => 'Isolation Contact',
        'email' => 'isolation@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenantB, $brandB));
    $contactId = $contactResponse->json('data.id');

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);
    actingAs($adminA);

    getJson("/api/v1/contacts/{$contactId}", crmHeaders($tenantA, $brandA))->assertNotFound();
});

it('E2-F1-I2 scopes contact listings by brand', function () {
    $tenant = Tenant::factory()->create();
    $brandOne = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $brandTwo = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brandOne->id,
    ]);
    $admin->assignRole('Admin');

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brandOne);
    actingAs($admin);

    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Brand Filter Co',
    ], crmHeaders($tenant, $brandOne));
    $companyId = $companyResponse->json('data.id');

    postJson('/api/v1/contacts', [
        'name' => 'Brand Filter Contact',
        'email' => 'brand-filter@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brandOne))->assertCreated();

    $otherBrandResponse = getJson('/api/v1/contacts', crmHeaders($tenant, $brandTwo));
    $otherBrandResponse->assertOk();
    $otherBrandResponse->assertJsonPath('data', []);

    $sameBrandResponse = getJson('/api/v1/contacts', crmHeaders($tenant, $brandOne));
    $sameBrandResponse->assertOk();
    $sameBrandResponse->assertJsonCount(1, 'data');
});

it('E2-F1-I2 logs audit entries when contacts are updated', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Audit Log Co',
    ], crmHeaders($tenant, $brand));
    $companyId = $companyResponse->json('data.id');

    $contactResponse = postJson('/api/v1/contacts', [
        'name' => 'Audit Log Contact',
        'email' => 'audit@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brand));
    $contactId = $contactResponse->json('data.id');

    $correlation = (string) Str::uuid();
    $updateResponse = patchJson("/api/v1/contacts/{$contactId}", [
        'phone' => '+15550001234',
    ], crmHeaders($tenant, $brand, $correlation));
    $updateResponse->assertOk();

    $log = AuditLog::query()
        ->where('auditable_type', \App\Models\Contact::class)
        ->where('auditable_id', $contactId)
        ->where('action', 'contact.updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->changes['correlation_id'] ?? null)->toEqual($correlation);
    expect($log->changes)->toHaveKey('phone_hash');
});

it('E2-F1-I2 validates GDPR consent flags', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $companyResponse = postJson('/api/v1/companies', [
        'name' => 'Consent Co',
    ], crmHeaders($tenant, $brand));
    $companyId = $companyResponse->json('data.id');

    $response = postJson('/api/v1/contacts', [
        'name' => 'Consent Contact',
        'email' => 'consent@example.com',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => false,
        'gdpr_data_processing_opt_in' => true,
    ], crmHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});
