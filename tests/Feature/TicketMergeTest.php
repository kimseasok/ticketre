<?php

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMerge;
use App\Models\TicketRelationship;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

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
});

function mergeHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function bindTenantContext(Tenant $tenant, ?Brand $brand = null): void
{
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $permissionRegistrar = app(PermissionRegistrar::class);
    $permissionRegistrar->forgetCachedPermissions();

    if (method_exists($permissionRegistrar, 'clearPermissionsCollection')) {
        $permissionRegistrar->clearPermissionsCollection();
    }
}

it('E1-F6-I2 merges tickets and aggregates history', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    bindTenantContext($tenant, $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $primary = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'metadata' => ['source' => 'primary'],
        'custom_fields' => [
            ['key' => 'priority_override', 'type' => 'string', 'value' => 'non-production'],
        ],
    ]);

    $secondary = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'metadata' => ['source' => 'secondary', 'legacy_id' => 'abc-123'],
        'custom_fields' => [
            ['key' => 'legacy_case', 'type' => 'string', 'value' => 'LC-42'],
        ],
    ]);

    Message::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $secondary->id,
        'user_id' => $admin->id,
    ]);

    Attachment::create([
        'tenant_id' => $tenant->id,
        'attachable_type' => Ticket::class,
        'attachable_id' => $secondary->id,
        'disk' => 'local',
        'path' => 'attachments/demo.txt',
        'size' => 512,
        'mime_type' => 'text/plain',
    ]);

    TicketEvent::factory()->forTicket($secondary)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'type' => TicketEvent::TYPE_ASSIGNED,
    ]);

    actingAs($admin);

    $payload = [
        'primary_ticket_id' => $primary->id,
        'secondary_ticket_id' => $secondary->id,
        'correlation_id' => (string) Str::uuid(),
    ];

    $response = postJson('/api/v1/ticket-merges', $payload, mergeHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.status', TicketMerge::STATUS_COMPLETED);
    $response->assertJsonPath('data.attributes.summary.messages_migrated', 2);

    $primary->refresh();
    $secondary->refresh();

    expect($primary->metadata['legacy_id'] ?? null)->toBe('abc-123');
    expect(collect($primary->metadata['merged_ticket_ids'] ?? [])->contains($secondary->id))->toBeTrue();
    expect($secondary->status)->toBe('closed');
    expect($secondary->metadata['merged_into_ticket_id'] ?? null)->toBe($primary->id);

    $messages = Message::query()->where('ticket_id', $primary->id)->count();
    expect($messages)->toBeGreaterThanOrEqual(2);

    $mergeRecord = TicketMerge::query()->firstOrFail();
    expect($mergeRecord->summary['events_migrated'] ?? null)->toBe(1);

    $relationshipExists = TicketRelationship::query()
        ->where('primary_ticket_id', $primary->id)
        ->where('related_ticket_id', $secondary->id)
        ->where('relationship_type', TicketRelationship::TYPE_MERGE)
        ->exists();

    expect($relationshipExists)->toBeTrue();

    $auditExists = AuditLog::query()
        ->where('action', 'ticket.merge.completed')
        ->where('auditable_id', $mergeRecord->getKey())
        ->exists();

    expect($auditExists)->toBeTrue();
});

it('E1-F6-I2 enforces RBAC for merges', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    bindTenantContext($tenant, $brand);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    [$primaryAdmin, $secondaryAdmin] = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    [$primaryAgent, $secondaryAgent] = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    [$primaryViewer, $secondaryViewer] = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    actingAs($admin);

    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primaryAdmin->id,
        'secondary_ticket_id' => $secondaryAdmin->id,
    ], mergeHeaders($tenant, $brand))->assertCreated();

    actingAs($agent);

    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primaryAgent->id,
        'secondary_ticket_id' => $secondaryAgent->id,
    ], mergeHeaders($tenant, $brand))->assertCreated();

    actingAs($viewer);

    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primaryViewer->id,
        'secondary_ticket_id' => $secondaryViewer->id,
    ], mergeHeaders($tenant, $brand))->assertForbidden();
});

it('E1-F6-I2 blocks merges across brands', function () {
    $tenant = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    bindTenantContext($tenant, $brandA);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brandA->id]);
    $admin->assignRole('Admin');

    $primary = Ticket::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brandA->id]);
    $secondary = Ticket::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brandB->id]);

    actingAs($admin);

    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primary->id,
        'secondary_ticket_id' => $secondary->id,
    ], mergeHeaders($tenant, $brandA))->assertStatus(422);
});

it('E1-F6-I2 rejects self-merge attempts', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    bindTenantContext($tenant, $brand);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    actingAs($admin);

    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $ticket->id,
        'secondary_ticket_id' => $ticket->id,
    ], mergeHeaders($tenant, $brand))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F6-I2 lists merges with tenant isolation', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    bindTenantContext($tenantA, $brandA);
    $adminA->assignRole('Admin');

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    bindTenantContext($tenantB, $brandB);
    $adminB->assignRole('Admin');
    app()->forgetInstance('currentTenant');
    app()->forgetInstance('currentBrand');

    [$primaryA, $secondaryA] = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);

    [$primaryB, $secondaryB] = Ticket::factory()->count(2)->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);

    actingAs($adminA);
    bindTenantContext($tenantA, $brandA);
    postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primaryA->id,
        'secondary_ticket_id' => $secondaryA->id,
    ], mergeHeaders($tenantA, $brandA))->assertCreated();

    actingAs($adminB);
    bindTenantContext($tenantB, $brandB);
    $tenantBResponse = postJson('/api/v1/ticket-merges', [
        'primary_ticket_id' => $primaryB->id,
        'secondary_ticket_id' => $secondaryB->id,
    ], mergeHeaders($tenantB, $brandB));

    $tenantBResponse->assertCreated();

    actingAs($adminA);
    bindTenantContext($tenantA, $brandA);

    $listResponse = getJson('/api/v1/ticket-merges', mergeHeaders($tenantA, $brandA));
    $listResponse->assertOk();
    $listResponse->assertJsonCount(1, 'data');

    $merge = TicketMerge::query()->where('tenant_id', $tenantA->id)->firstOrFail();
    $listResponse->assertJsonPath('data.0.id', (string) $merge->id);
});
