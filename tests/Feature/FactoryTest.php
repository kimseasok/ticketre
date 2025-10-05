<?php

use App\Models\Ticket;

it('creates ticket via factory', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->subject)->not->toBeEmpty();
    expect($ticket->tenant_id)->not->toBeNull();
});
