<?php

use App\Models\Ticket;
use App\Models\Tenant;

it('isolates tenant data', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    $ticketA = Ticket::factory()->create(['tenant_id' => $tenantA->id]);
    $ticketB = Ticket::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenant', $tenantA);

    $visible = Ticket::query()->get();
    expect($visible->pluck('tenant_id'))->toContain($tenantA->id)
        ->not->toContain($tenantB->id);
});
