<?php

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('E1-F5-I1 allows agent to create public message via API', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Sanctum::actingAs($user, ['*'], 'sanctum');

    postJson(route('api.tickets.messages.store', ['ticket' => $ticket]), [
        'body' => 'Customer-facing note',
        'visibility' => Message::VISIBILITY_PUBLIC,
    ])
        ->assertCreated()
        ->assertJsonPath('data.visibility', Message::VISIBILITY_PUBLIC);

    $message = Message::query()->latest()->first();

    expect($message)->not->toBeNull()
        ->and($message->author_role)->toBe('Agent');

    $audit = AuditLog::query()
        ->where('auditable_id', $message->id)
        ->where('auditable_type', Message::class)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->changes['body_length'] ?? null)->toBe(mb_strlen('Customer-facing note'));
})->group('E1-F5-I1');

it('E1-F5-I1 returns validation error when body is missing', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Sanctum::actingAs($user, ['*'], 'sanctum');

    postJson(route('api.tickets.messages.store', ['ticket' => $ticket]), [
        'visibility' => Message::VISIBILITY_PUBLIC,
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
})->group('E1-F5-I1');

it('E1-F5-I1 prevents viewer from creating messages', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Viewer');

    Sanctum::actingAs($user, ['*'], 'sanctum');

    postJson(route('api.tickets.messages.store', ['ticket' => $ticket]), [
        'body' => 'Should fail',
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_FORBIDDEN');
})->group('E1-F5-I1');

it('E1-F5-I1 lists messages for agents including internal notes', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $user->id,
        'visibility' => Message::VISIBILITY_PUBLIC,
    ]);

    Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $user->id,
        'visibility' => Message::VISIBILITY_INTERNAL,
    ]);

    Sanctum::actingAs($user, ['*'], 'sanctum');

    $response = getJson(route('api.tickets.messages.index', ['ticket' => $ticket]))
        ->assertOk()
        ->json('data');

    expect($response)->toHaveCount(2);
})->group('E1-F5-I1');

it('E1-F5-I1 hides internal notes for portal audience', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $user->id,
        'visibility' => Message::VISIBILITY_PUBLIC,
    ]);

    Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
        'user_id' => $user->id,
        'visibility' => Message::VISIBILITY_INTERNAL,
    ]);

    Sanctum::actingAs($user, ['*'], 'sanctum');

    $response = getJson(route('api.tickets.messages.index', ['ticket' => $ticket, 'audience' => 'portal']))
        ->assertOk()
        ->json('data');

    expect($response)->toHaveCount(1)
        ->and($response[0]['visibility'])->toBe(Message::VISIBILITY_PUBLIC);
})->group('E1-F5-I1');

it('E1-F5-I1 enforces tenant isolation when listing messages', function () {
    $ticket = Ticket::factory()->create();
    $otherTenantUser = User::factory()->create();
    $otherTenantUser->assignRole('Agent');

    Sanctum::actingAs($otherTenantUser, ['*'], 'sanctum');

    getJson(route('api.tickets.messages.index', ['ticket' => $ticket]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ERR_NOT_FOUND');
})->group('E1-F5-I1');

it('E1-F5-I1 allows agent to override sent_at when creating a message', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Sanctum::actingAs($user, ['*'], 'sanctum');

    $timestamp = now()->subMinutes(5)->setSecond(0)->setMicrosecond(0)->toIso8601String();

    postJson(route('api.tickets.messages.store', ['ticket' => $ticket]), [
        'body' => 'Time travel note',
        'visibility' => Message::VISIBILITY_PUBLIC,
        'sent_at' => $timestamp,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sent_at', $timestamp);

    $message = Message::query()->latest()->first();

    expect($message)
        ->not->toBeNull()
        ->and($message->sent_at?->toIso8601String())->toBe($timestamp);
})->group('E1-F5-I1');

it('E1-F5-I1 rejects invalid visibility values', function () {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Agent');

    Sanctum::actingAs($user, ['*'], 'sanctum');

    postJson(route('api.tickets.messages.store', ['ticket' => $ticket]), [
        'body' => 'Visibility rules',
        'visibility' => 'secret',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION')
        ->assertJsonPath('error.details.visibility.0', 'The selected visibility is invalid.');
})->group('E1-F5-I1');

it('E1-F5-I1 applies message policy matrix across roles', function () {
    $ticket = Ticket::factory()->create();
    $message = Message::factory()->create([
        'ticket_id' => $ticket->id,
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);

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

    expect($admin->can('view', $message))->toBeTrue()
        ->and($admin->can('create', Message::class))->toBeTrue()
        ->and($agent->can('view', $message))->toBeTrue()
        ->and($agent->can('create', Message::class))->toBeTrue()
        ->and($viewer->can('view', $message))->toBeTrue()
        ->and($viewer->can('create', Message::class))->toBeFalse();
})->group('E1-F5-I1');
