<?php

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function relationshipHeadersFor(Ticket $ticket): array
{
    $ticket->loadMissing('tenant', 'brand');

    $headers = [
        'X-Tenant' => $ticket->tenant->slug,
        'Accept' => 'application/json',
    ];

    if ($ticket->brand) {
        $headers['X-Brand'] = $ticket->brand->slug;
    }

    return $headers;
}

it('E1-F6-I1 allows admins to create ticket relationships via API', function () {
    $primary = Ticket::factory()->create();
    $related = Ticket::factory()->create([
        'tenant_id' => $primary->tenant_id,
        'brand_id' => $primary->brand_id,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $primary->tenant_id,
        'brand_id' => $primary->brand_id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson(
        "/api/v1/tickets/{$primary->getKey()}/relationships",
        [
            'related_ticket_id' => $related->getKey(),
            'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
            'context' => ['notes' => 'Linked by automated test'],
        ],
        relationshipHeadersFor($primary)
    );

    $response->assertCreated();

    $data = $response->json('data');
    expect($data)
        ->toHaveKey('relationship_type', TicketRelationship::TYPE_DUPLICATE)
        ->and($data['related_ticket']['id'])->toBe($related->getKey());

    expect(TicketRelationship::query()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'ticket.relationship.created')->count())->toBe(1);
});

it('E1-F6-I1 prevents circular merge relationships', function () {
    $relationship = TicketRelationship::factory()->create([
        'relationship_type' => TicketRelationship::TYPE_MERGED,
    ]);

    $primary = Ticket::find($relationship->primary_ticket_id);
    $related = Ticket::find($relationship->related_ticket_id);

    $admin = User::factory()->create([
        'tenant_id' => $related->tenant_id,
        'brand_id' => $related->brand_id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = postJson(
        "/api/v1/tickets/{$related->getKey()}/relationships",
        [
            'related_ticket_id' => $primary->getKey(),
            'relationship_type' => TicketRelationship::TYPE_MERGED,
        ],
        relationshipHeadersFor($related)
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('ERR_VALIDATION');
});

it('E1-F6-I1 rejects viewers attempting to create ticket relationships', function () {
    $primary = Ticket::factory()->create();
    $related = Ticket::factory()->create([
        'tenant_id' => $primary->tenant_id,
        'brand_id' => $primary->brand_id,
    ]);

    $viewer = User::factory()->create([
        'tenant_id' => $primary->tenant_id,
        'brand_id' => $primary->brand_id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = postJson(
        "/api/v1/tickets/{$primary->getKey()}/relationships",
        [
            'related_ticket_id' => $related->getKey(),
            'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
        ],
        relationshipHeadersFor($primary)
    );

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('ERR_HTTP_403');
});

it('E1-F6-I1 enforces policy matrix across roles', function (string $role, bool $canManage) {
    $relationship = TicketRelationship::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $relationship->tenant_id,
        'brand_id' => $relationship->brand_id,
    ]);
    $user->assignRole($role);

    expect($user->can('view', $relationship))->toBeTrue();
    expect($user->can('create', TicketRelationship::class))->toBe($canManage);
    expect($user->can('update', $relationship))->toBe($canManage);
})->with([
    ['Admin', true],
    ['Agent', true],
    ['Viewer', false],
]);

it('E1-F6-I1 isolates relationships by tenant context', function () {
    $relationship = TicketRelationship::factory()->create();

    $foreignTicket = Ticket::factory()->create();

    $admin = User::factory()->create([
        'tenant_id' => $foreignTicket->tenant_id,
        'brand_id' => $foreignTicket->brand_id,
    ]);
    $admin->assignRole('Admin');

    actingAs($admin);

    $response = getJson(
        "/api/v1/tickets/{$relationship->primary_ticket_id}/relationships",
        relationshipHeadersFor($foreignTicket)
    );

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('ERR_HTTP_404');
});
