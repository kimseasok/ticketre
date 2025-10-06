<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ContactService;
use App\Services\TicketService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function tenantHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F6-I2 logs ticket creation with redacted fields', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $service = app(TicketService::class);

    $subject = 'Escalate '.Str::uuid();

    $ticket = $service->create([
        'brand_id' => $brand->id,
        'subject' => $subject,
        'status' => 'open',
        'priority' => 'high',
        'metadata' => ['email' => 'vip@example.com'],
    ], $admin);

    $log = AuditLog::query()
        ->where('action', 'ticket.created')
        ->where('auditable_id', $ticket->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->brand_id)->toBe($brand->id)
        ->and($log->changes['sensitive']['subject_digest'] ?? null)->toBe(hash('sha256', $subject))
        ->and($log->changes['sensitive']['metadata_keys'] ?? null)->toBe(['email'])
        ->and($log->changes)->not->toHaveKey('subject');
});

it('E2-F6-I2 logs ticket updates with masked metadata and subject digest', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $service = app(TicketService::class);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'subject' => 'Original Subject',
        'metadata' => ['phone' => '123'],
    ]);

    $service->update($ticket, [
        'subject' => 'Updated Subject',
        'metadata' => ['phone' => '456', 'notes' => 'sensitive'],
        'status' => 'pending',
    ], $admin);

    $log = AuditLog::query()
        ->where('action', 'ticket.updated')
        ->where('auditable_id', $ticket->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['subject_digest'] ?? null)
            ->toBe([
                'old' => hash('sha256', 'Original Subject'),
                'new' => hash('sha256', 'Updated Subject'),
            ])
        ->and($log->changes['metadata_keys'] ?? null)->toBe([
            'old' => ['phone'],
            'new' => ['phone', 'notes'],
        ])
        ->and($log->changes['status'] ?? null)
            ->toBe(['old' => 'open', 'new' => 'pending']);
});

it('E2-F6-I2 logs contact updates with hashed personal data', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $service = app(ContactService::class);

    $contact = $service->create([
        'name' => 'Casey Customer',
        'email' => 'casey@example.com',
        'phone' => '+15551234567',
    ], $admin);

    $service->update($contact, [
        'email' => 'updated@example.com',
        'phone' => '+15557654321',
        'name' => 'Casey C.',
    ], $admin);

    $log = AuditLog::query()
        ->where('action', 'contact.updated')
        ->where('auditable_id', $contact->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['email_hash'] ?? null)
            ->toBe([
                'old' => hash('sha256', 'casey@example.com'),
                'new' => hash('sha256', 'updated@example.com'),
            ])
        ->and($log->changes['phone_hash'] ?? null)
            ->toBe([
                'old' => hash('sha256', '+15551234567'),
                'new' => hash('sha256', '+15557654321'),
            ])
        ->and($log->changes['name'] ?? null)
            ->toBe(['old' => 'Casey Customer', 'new' => 'Casey C.']);
});

it('E2-F6-I2 prevents agents from listing audit logs', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = getJson('/api/v1/audit-logs', tenantHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E2-F6-I2 lists audit logs for admins with tenant isolation', function () {
    $tenantA = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantA);
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    app()->instance('currentBrand', $brandA);
    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    $tenantB = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantB);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);
    app()->instance('currentBrand', $brandB);
    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $adminB->assignRole('Admin');

    actingAs($adminA);
    app()->instance('currentTenant', $tenantA);
    app()->instance('currentBrand', $brandA);

    $ticketService = app(TicketService::class);
    $ticketA = $ticketService->create([
        'brand_id' => $brandA->id,
        'subject' => 'Tenant A Ticket',
        'status' => 'open',
        'priority' => 'low',
    ], $adminA);

    actingAs($adminB);
    app()->instance('currentTenant', $tenantB);
    app()->instance('currentBrand', $brandB);
    $ticketService->create([
        'brand_id' => $brandB->id,
        'subject' => 'Tenant B Ticket',
        'status' => 'open',
        'priority' => 'low',
    ], $adminB);

    actingAs($adminA);

    $response = getJson('/api/v1/audit-logs?action=ticket.created', tenantHeaders($tenantA, $brandA));

    $response->assertOk();
    $data = $response->json('data');

    expect(collect($data)->pluck('tenant_id')->unique()->all())->toBe([$tenantA->id])
        ->and(collect($data)->pluck('auditable_id'))->toContain($ticketA->id)
        ->and(collect($data)->pluck('auditable_id'))->not->toContain($ticketA->id + 1);
});

it('E2-F6-I2 validates audit log filters', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = getJson('/api/v1/audit-logs?auditable_type=unknown', tenantHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F6-I2 enforces policy matrix across roles', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');

    actingAs($admin);
    getJson('/api/v1/audit-logs', tenantHeaders($tenant, $brand))->assertOk();

    actingAs($agent);
    getJson('/api/v1/audit-logs', tenantHeaders($tenant, $brand))->assertForbidden();

    actingAs($viewer);
    getJson('/api/v1/audit-logs', tenantHeaders($tenant, $brand))->assertForbidden();
});
