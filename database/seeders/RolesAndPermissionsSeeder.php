<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'tickets.view',
            'tickets.manage',
            'tickets.redact',
            'contacts.view',
            'contacts.manage',
            'contacts.anonymize',
            'knowledge.manage',
            'knowledge.view',
            'reports.view',
            'integrations.manage',
            'audit_logs.view',
            'roles.view',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => 'SuperAdmin',
            'slug' => 'super-admin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ], [
            'description' => 'Global super administrator with unrestricted access across tenants.',
            'is_system' => true,
        ]);

        $superAdmin->syncPermissions($permissions);

        /** @var TenantRoleProvisioner $provisioner */
        $provisioner = app(TenantRoleProvisioner::class);

        Tenant::query()->each(function (Tenant $tenant) use ($provisioner): void {
            $provisioner->syncSystemRoles($tenant);
        });
    }
}
