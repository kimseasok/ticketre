<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

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

function directoryHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F1-I2 allows admins to create contacts with tags and audit logging', function () {
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
        'name' => 'Acme Corporation',
        'domain' => 'acme.test',
        'metadata' => ['segment' => 'enterprise'],
    ], directoryHeaders($tenant, $brand));

    $companyResponse->assertCreated();
    $companyId = $companyResponse->json('data.id');

    $response = postJson('/api/v1/contacts', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'phone' => '+1-555-1234',
        'company_id' => $companyId,
        'gdpr_marketing_opt_in' => true,
        'gdpr_tracking_opt_in' => false,
        'tags' => ['vip', 'beta tester'],
        'metadata' => ['preferred_language' => 'en'],
    ], array_merge(directoryHeaders($tenant, $brand), [
        'X-Correlation-ID' => 'contact-create-demo',
    ]));

    $response->assertCreated();
    $contactId = $response->json('data.id');

    $contact = Contact::with(['tags'])->findOrFail($contactId);

    expect($contact->gdpr_marketing_opt_in)->toBeTrue();
    expect($contact->gdpr_tracking_opt_in)->toBeFalse();
    expect($contact->tags->pluck('name'))->toContain('Vip', 'Beta Tester');

    $companyLog = AuditLog::query()
        ->where('auditable_type', Company::class)
        ->where('auditable_id', $companyId)
        ->where('action', 'company.created')
        ->first();

    $contactLog = AuditLog::query()
        ->where('auditable_type', Contact::class)
        ->where('auditable_id', $contactId)
        ->where('action', 'contact.created')
        ->first();

    expect($companyLog)->not->toBeNull();
    expect($contactLog)->not->toBeNull();
});

it('E2-F1-I2 enforces unique email per tenant even with soft deletes', function () {
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

    $company = Company::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($admin);

    $payload = [
        'name' => 'Existing Contact',
        'email' => 'unique@example.test',
        'company_id' => $company->id,
        'gdpr_marketing_opt_in' => true,
        'gdpr_tracking_opt_in' => true,
    ];

    postJson('/api/v1/contacts', $payload, directoryHeaders($tenant, $brand))->assertCreated();

    $duplicate = postJson('/api/v1/contacts', array_merge($payload, ['name' => 'Duplicate Name']), directoryHeaders($tenant, $brand));
    $duplicate->assertUnprocessable();
    $duplicate->assertJsonPath('error.code', 'ERR_VALIDATION');

    $existing = Contact::query()->where('email', 'unique@example.test')->firstOrFail();
    $existing->delete();

    $secondAttempt = postJson('/api/v1/contacts', array_merge($payload, ['name' => 'Second Attempt']), directoryHeaders($tenant, $brand));
    $secondAttempt->assertCreated();
});

it('E2-F1-I2 enforces contact policy matrix', function (string $role, int $indexStatus, int $storeStatus) {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole($role);

    $company = Company::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($user);

    $indexResponse = getJson('/api/v1/contacts', directoryHeaders($tenant, $brand));
    $indexResponse->assertStatus($indexStatus);

    $storeResponse = postJson('/api/v1/contacts', [
        'name' => 'Matrix Tester',
        'email' => $role.'@example.test',
        'company_id' => $company->id,
        'gdpr_marketing_opt_in' => true,
        'gdpr_tracking_opt_in' => true,
    ], directoryHeaders($tenant, $brand));

    $storeResponse->assertStatus($storeStatus);

    if ($storeStatus === 201) {
        $storeResponse->assertJsonPath('data.email', $role.'@example.test');
    } else {
        $storeResponse->assertJsonPath('error.code', 'ERR_HTTP_403');
    }
})->with([
    ['Admin', 200, 201],
    ['Agent', 200, 201],
    ['Viewer', 200, 403],
]);

it('E2-F1-I2 prevents cross-tenant access to contacts', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    actingAs($adminA);

    $otherTenantCompany = Company::factory()->create(['tenant_id' => $tenantB->id]);
    $foreignContact = Contact::factory()->create([
        'tenant_id' => $tenantB->id,
        'company_id' => $otherTenantCompany->id,
        'email' => 'foreign@example.test',
    ]);

    $showResponse = getJson('/api/v1/contacts/'.$foreignContact->id, directoryHeaders($tenantA, $brandA));
    $showResponse->assertStatus(404);
});

it('E2-F1-I2 filters contacts by tags and marketing consent', function () {
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

    $company = Company::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($admin);

    postJson('/api/v1/contacts', [
        'name' => 'VIP Contact',
        'email' => 'vip@example.test',
        'company_id' => $company->id,
        'gdpr_marketing_opt_in' => true,
        'gdpr_tracking_opt_in' => false,
        'tags' => ['vip'],
    ], directoryHeaders($tenant, $brand))->assertCreated();

    postJson('/api/v1/contacts', [
        'name' => 'Standard Contact',
        'email' => 'standard@example.test',
        'company_id' => $company->id,
        'gdpr_marketing_opt_in' => false,
        'gdpr_tracking_opt_in' => false,
        'tags' => ['general'],
    ], directoryHeaders($tenant, $brand))->assertCreated();

    $filterResponse = getJson('/api/v1/contacts?tags=vip&marketing_opt_in=1', directoryHeaders($tenant, $brand));
    $filterResponse->assertOk();
    $filterResponse->assertJsonCount(1, 'data');
    $filterResponse->assertJsonPath('data.0.email', 'vip@example.test');
});

it('E2-F1-I2 protects contact APIs for unauthenticated requests', function () {
    $tenant = Tenant::factory()->create();

    $response = getJson('/api/v1/contacts', directoryHeaders($tenant));
    $response->assertStatus(401);
    $response->assertJsonPath('message', 'Unauthenticated.');
});

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

    $createResponse = postJson('/api/v1/companies', [
        'name' => 'Portal Industries',
        'domain' => 'portal.test',
        'metadata' => ['tier' => 'gold'],
    ], directoryHeaders($tenant, $brand));

    $createResponse->assertCreated();
    $companyId = $createResponse->json('data.id');

    $updateResponse = patchJson('/api/v1/companies/'.$companyId, [
        'metadata' => ['tier' => 'platinum'],
    ], directoryHeaders($tenant, $brand));
    $updateResponse->assertOk();
    $updateResponse->assertJsonPath('data.metadata.tier', 'platinum');

    $deleteResponse = deleteJson('/api/v1/companies/'.$companyId, [], directoryHeaders($tenant, $brand));
    $deleteResponse->assertNoContent();

    $log = AuditLog::query()
        ->where('auditable_type', Company::class)
        ->where('auditable_id', $companyId)
        ->where('action', 'company.deleted')
        ->first();

    expect($log)->not->toBeNull();
});
