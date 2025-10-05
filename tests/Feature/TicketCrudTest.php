<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('creates ticket record', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->fresh())->not->toBeNull();
});

it('updates ticket record', function () {
    $ticket = Ticket::factory()->create();

    $ticket->update(['status' => 'pending']);

    expect($ticket->refresh()->status)->toBe('pending');
});

it('deletes ticket record', function () {
    $ticket = Ticket::factory()->create();

    $ticket->delete();

    expect($ticket->trashed())->toBeTrue();
});
