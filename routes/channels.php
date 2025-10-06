<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenants.{tenantId}.brands.{brandId}.tickets', function ($user, int $tenantId, int $brandId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    if ($brandId !== 0 && $user->brand_id !== $brandId) {
        return false;
    }

    return $user->can('tickets.view');
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
