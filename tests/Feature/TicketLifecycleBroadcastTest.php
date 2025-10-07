<?php

use App\Broadcasting\Events\TicketLifecycleBroadcast;
use App\Jobs\BroadcastTicketEventJob;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\TicketLifecycleBroadcaster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

function actingHeadersForTenant(Tenant $tenant, Brand $brand): array
{
    return [
        'X-Tenant' => $tenant->slug,
        'X-Brand' => $brand->slug,
    ];
}

it('E1-F8-I2 records and queues broadcast when ticket created via api', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole('Admin');

    $this->actingAs($user);

    $response = $this->withHeaders(actingHeadersForTenant($tenant, $brand))->postJson('/api/v1/tickets', [
        'subject' => 'Broadcast ticket',
        'status' => 'open',
        'priority' => 'high',
    ]);

    $response->assertCreated();

    $ticket = Ticket::first();

    expect($ticket)->not->toBeNull();

    $this->assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEvent::TYPE_CREATED,
    ]);

    Bus::assertDispatched(BroadcastTicketEventJob::class);
});

dataset('ticket-event-view-roles', [
    'admin' => 'Admin',
    'agent' => 'Agent',
    'viewer' => 'Viewer',
]);

it('E1-F8-I2 enforces policy for viewing ticket events', function (string $role) {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole($role);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $event = app(TicketLifecycleBroadcaster::class)->record($ticket, TicketEvent::TYPE_CREATED, [], $user, TicketEvent::VISIBILITY_INTERNAL, false);

    $this->actingAs($user);

    $response = $this->withHeaders(actingHeadersForTenant($tenant, $brand))->getJson("/api/v1/tickets/{$ticket->id}/events");

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $event->id);
})->with('ticket-event-view-roles');

it('E1-F8-I2 prevents users without manage permission from creating ticket events', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $this->actingAs($viewer);

    $response = $this->withHeaders(actingHeadersForTenant($tenant, $brand))->postJson("/api/v1/tickets/{$ticket->id}/events", [
        'type' => TicketEvent::TYPE_UPDATED,
        'payload' => [],
    ]);

    $response->assertForbidden();
});

it('E1-F8-I2 isolates ticket events by tenant', function () {
    $tenantA = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantA);
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    app()->instance('currentBrand', $brandA);
    $tenantB = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantB);
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);
    app()->instance('currentBrand', $brandB);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);
    $adminA->assignRole('Admin');

    $ticketA = Ticket::factory()->create([
        'tenant_id' => $tenantA->id,
        'brand_id' => $brandA->id,
    ]);

    app(TicketLifecycleBroadcaster::class)->record($ticketA, TicketEvent::TYPE_CREATED, [], $adminA, TicketEvent::VISIBILITY_INTERNAL, false);

    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'brand_id' => $brandB->id,
    ]);
    $userB->assignRole('Admin');

    $this->actingAs($userB);

    $response = $this->withHeaders(actingHeadersForTenant($tenantB, $brandB))->getJson("/api/v1/tickets/{$ticketA->id}/events");

    $response->assertNotFound();
});

it('E1-F8-I2 validates ticket event store payload type', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole('Admin');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $this->actingAs($user);

    $response = $this->withHeaders(actingHeadersForTenant($tenant, $brand))->postJson("/api/v1/tickets/{$ticket->id}/events", [
        'type' => 'invalid.event',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F8-I2 dispatches broadcast event with normalized payload', function () {
    $event = TicketEvent::factory()->create([
        'payload' => ['changes' => ['status' => 'open']],
    ]);

    Event::fake();

    (new BroadcastTicketEventJob($event->id))->handle();

    Event::assertDispatched(TicketLifecycleBroadcast::class, function (TicketLifecycleBroadcast $broadcast) use ($event) {
        return $broadcast->event->is($event) && $broadcast->broadcastWith()['payload']['changes']['status'] === 'open';
    });
});

it('E1-F8-I2 returns ticket collection via api', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $user->assignRole('Agent');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $this->actingAs($user);

    $response = $this->withHeaders(actingHeadersForTenant($tenant, $brand))->getJson('/api/v1/tickets');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', (string) $ticket->id);
});
