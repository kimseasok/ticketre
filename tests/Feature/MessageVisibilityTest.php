<?php

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function actingHeadersFor(Ticket $ticket): array
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

it('E1-F5-I1 allows agents to create internal messages via API', function () {
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $payload = [
        'body' => 'Internal context '.Str::uuid(),
        'visibility' => Message::VISIBILITY_INTERNAL,
    ];

    $response = $this->postJson(
        "/api/v1/tickets/{$ticket->id}/messages",
        $payload,
        actingHeadersFor($ticket)
    );

    $response->assertCreated();
    $response->assertJsonPath('data.visibility', Message::VISIBILITY_INTERNAL);
    $response->assertJsonPath('data.author_role', Message::ROLE_AGENT);

    $message = Message::query()->where('ticket_id', $ticket->id)->first();
    expect($message)->not->toBeNull()
        ->and($message->visibility)->toBe(Message::VISIBILITY_INTERNAL);

    expect(AuditLog::query()->where('action', 'message.created')->count())->toBeGreaterThan(0);
});

it('E1-F5-I1 hides internal notes from viewer queries', function () {
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $viewer->assignRole('Viewer');

    $internal = Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $agent->id,
        'author_role' => Message::ROLE_AGENT,
        'visibility' => Message::VISIBILITY_INTERNAL,
    ]);

    $public = Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $agent->id,
        'author_role' => Message::ROLE_AGENT,
        'visibility' => Message::VISIBILITY_PUBLIC,
    ]);

    actingAs($viewer);

    $response = $this->getJson(
        "/api/v1/tickets/{$ticket->id}/messages",
        actingHeadersFor($ticket)
    );

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($public->id)
        ->not->toContain($internal->id);

    collect($response->json('data'))->each(function (array $message) {
        expect($message['visibility'])->toBe(Message::VISIBILITY_PUBLIC);
    });
});

it('E1-F5-I1 rejects invalid visibility values with validation schema', function () {
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = $this->postJson(
        "/api/v1/tickets/{$ticket->id}/messages",
        [
            'body' => 'Example body',
            'visibility' => 'private',
        ],
        actingHeadersFor($ticket)
    );

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F5-I1 enforces policy matrix across roles', function () {
    $ticket = Ticket::factory()->create();
    $admin = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $viewer->assignRole('Viewer');

    $message = Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $agent->id,
        'author_role' => Message::ROLE_AGENT,
        'visibility' => Message::VISIBILITY_INTERNAL,
    ]);

    app()->instance('currentTenant', $ticket->tenant);
    app()->instance('currentBrand', $ticket->brand);

    expect($admin->can('view', $message))->toBeTrue();
    expect($agent->can('view', $message))->toBeTrue();
    expect($viewer->can('view', $message))->toBeFalse();
});

it('E1-F5-I1 prevents cross-tenant access', function () {
    $foreignTicket = Ticket::factory()->create();
    $otherTicket = Ticket::factory()->create();

    $agent = User::factory()->create([
        'tenant_id' => $otherTicket->tenant_id,
        'brand_id' => $otherTicket->brand_id,
    ]);
    $agent->assignRole('Agent');

    actingAs($agent);

    $response = $this->getJson(
        "/api/v1/tickets/{$foreignTicket->id}/messages",
        actingHeadersFor($otherTicket)
    );

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E1-F5-I1 returns authorization error schema for viewers posting internal notes', function () {
    $ticket = Ticket::factory()->create();
    $viewer = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $viewer->assignRole('Viewer');

    actingAs($viewer);

    $response = $this->postJson(
        "/api/v1/tickets/{$ticket->id}/messages",
        [
            'body' => 'Attempted internal note',
            'visibility' => Message::VISIBILITY_INTERNAL,
        ],
        actingHeadersFor($ticket)
    );

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});
