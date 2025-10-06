<?php

use App\Models\Ticket;
use App\Models\User;

it('allows admin to update ticket', function () {
    $ticket = Ticket::factory()->create();
    app()->instance('currentTenant', $ticket->tenant);
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Admin');

    expect($user->can('update', $ticket))->toBeTrue();
});

it('prevents viewer from managing ticket', function () {
    $ticket = Ticket::factory()->create();
    app()->instance('currentTenant', $ticket->tenant);
    $user = User::factory()->create([
        'tenant_id' => $ticket->tenant_id,
        'brand_id' => $ticket->brand_id,
    ]);
    $user->assignRole('Viewer');

    expect($user->can('update', $ticket))->toBeFalse();
});
