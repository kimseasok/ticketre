<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketRelationshipService;
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

function relationshipHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E1-F6-I1 creates ticket relationships via API', function () {
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

    $primary = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $related = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($admin);

    $payload = [
        'primary_ticket_id' => $primary->id,
        'related_ticket_id' => $related->id,
        'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
        'context' => ['reason' => 'NON-PRODUCTION duplicate'],
        'correlation_id' => (string) Str::uuid(),
    ];

    $response = postJson('/api/v1/ticket-relationships', $payload, relationshipHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.relationship_type', TicketRelationship::TYPE_DUPLICATE);
    $response->assertJsonPath('data.attributes.correlation_id', $payload['correlation_id']);

    $relationship = TicketRelationship::query()->firstOrFail();
    expect($relationship->context)->toHaveKey('reason');

    $auditExists = AuditLog::query()
        ->where('action', 'ticket.relationship.created')
        ->where('auditable_id', $relationship->getKey())
        ->exists();

    expect($auditExists)->toBeTrue();
});

it('E1-F6-I1 prevents circular merge relationships', function () {
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

    $tickets = Ticket::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($admin);

    $service = app(TicketRelationshipService::class);
    $service->create([
        'primary_ticket_id' => $tickets[0]->id,
        'related_ticket_id' => $tickets[1]->id,
        'relationship_type' => TicketRelationship::TYPE_MERGE,
    ], $admin);

    $service->create([
        'primary_ticket_id' => $tickets[1]->id,
        'related_ticket_id' => $tickets[2]->id,
        'relationship_type' => TicketRelationship::TYPE_MERGE,
    ], $admin);

    $response = postJson('/api/v1/ticket-relationships', [
        'primary_ticket_id' => $tickets[2]->id,
        'related_ticket_id' => $tickets[0]->id,
        'relationship_type' => TicketRelationship::TYPE_MERGE,
    ], relationshipHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F6-I1 policy matrix aligns with RBAC roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    expect($admin->can('create', TicketRelationship::class))->toBeTrue();
    expect($agent->can('create', TicketRelationship::class))->toBeTrue();
    expect($viewer->can('create', TicketRelationship::class))->toBeFalse();
    expect($viewer->can('viewAny', TicketRelationship::class))->toBeTrue();
});

it('E1-F6-I1 enforces tenant isolation on relationships', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    $adminB->assignRole('Admin');

    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);

    actingAs($adminA);

    $primary = Ticket::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $related = Ticket::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);

    $relationship = app(TicketRelationshipService::class)->create([
        'primary_ticket_id' => $primary->id,
        'related_ticket_id' => $related->id,
        'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
    ], $adminA);

    actingAs($adminB);
    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);

    $response = getJson(sprintf('/api/v1/ticket-relationships/%d', $relationship->getKey()), relationshipHeaders($tenantB, $brandB));

    $response->assertStatus(404);
});

it('E1-F6-I1 forbids viewers from creating relationships', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    $tickets = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($viewer);

    $response = postJson('/api/v1/ticket-relationships', [
        'primary_ticket_id' => $tickets[0]->id,
        'related_ticket_id' => $tickets[1]->id,
        'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
    ], relationshipHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E1-F6-I1 updates relationship context via API', function () {
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

    $primary = Ticket::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $related = Ticket::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $relationship = app(TicketRelationshipService::class)->create([
        'primary_ticket_id' => $primary->id,
        'related_ticket_id' => $related->id,
        'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
    ], $admin);

    $payload = [
        'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
        'context' => ['note' => 'updated context'],
    ];

    $response = patchJson(
        sprintf('/api/v1/ticket-relationships/%d', $relationship->getKey()),
        $payload,
        relationshipHeaders($tenant, $brand)
    );

    $response->assertOk();
    $response->assertJsonPath('data.attributes.context.note', 'updated context');

    $updated = $relationship->fresh();
    expect($updated->context)->toHaveKey('note');
});
