<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\TenantRoleProvisioner;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    }
}
