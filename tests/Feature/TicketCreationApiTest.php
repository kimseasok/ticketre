<?php

use App\Models\Brand;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantRoleProvisioner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

function ticketHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E1-F1-I5 allows admins to create tickets via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $contact = Contact::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    actingAs($admin);

    $payload = [
        'subject' => 'API created ticket',
        'status' => 'open',
        'priority' => 'high',
        'contact_id' => $contact->id,
        'metadata' => ['source' => 'api', 'tags' => ['vip']],
        'custom_fields' => [
            [
                'key' => 'order_id',
                'type' => 'string',
                'value' => 'INV-1001',
            ],
            [
                'key' => 'urgent',
                'type' => 'boolean',
                'value' => true,
            ],
        ],
    ];

    $response = postJson('/api/v1/tickets', $payload, ticketHeaders($tenant, $brand) + ['X-Correlation-ID' => 'e1f1i5-demo']);

    $response->assertCreated();
    $response->assertJsonPath('data.type', 'tickets');
    $response->assertJsonPath('data.attributes.subject', 'API created ticket');
    $response->assertJsonPath('data.attributes.custom_fields.0.key', 'order_id');
    $response->assertJsonPath('data.links.self', route('api.tickets.show', ['ticket' => $response->json('data.id')]));

    app()->instance('currentTenant', $tenant);

    $ticket = Ticket::query()->latest('id')->firstOrFail();
    expect($ticket->custom_fields)->toHaveCount(2);
    expect($ticket->metadata)->toMatchArray(['source' => 'api', 'tags' => ['vip']]);
});

it('E1-F1-I5 allows agents to create tickets via API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = postJson('/api/v1/tickets', [
        'subject' => 'Agent created',
        'status' => 'open',
        'priority' => 'medium',
        'custom_fields' => [],
    ], ticketHeaders($tenant, $brand));

    $response->assertCreated();
});

it('E1-F1-I5 prevents viewers from creating tickets', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson('/api/v1/tickets', [
        'subject' => 'Viewer attempt',
        'status' => 'open',
        'priority' => 'medium',
    ], ticketHeaders($tenant, $brand));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E1-F1-I5 rejects unauthenticated requests', function () {
    $tenant = Tenant::factory()->create();

    $response = postJson('/api/v1/tickets', [
        'subject' => 'Unauthorized',
        'status' => 'open',
        'priority' => 'low',
    ], ticketHeaders($tenant));

    $response->assertUnauthorized();
});

it('E1-F1-I5 validates custom field payloads', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson('/api/v1/tickets', [
        'subject' => 'Invalid custom field',
        'status' => 'open',
        'priority' => 'low',
        'custom_fields' => [
            [
                'key' => 'bad',
                'type' => 'number',
                'value' => 'not-a-number',
            ],
        ],
    ], ticketHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F1-I5 enforces tenant isolation for related models', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $foreignContact = Contact::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    actingAs($admin);

    $response = postJson('/api/v1/tickets', [
        'subject' => 'Isolation check',
        'status' => 'open',
        'priority' => 'medium',
        'contact_id' => $foreignContact->id,
    ], ticketHeaders($tenant, $brand));

    $response->assertUnprocessable();
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});
