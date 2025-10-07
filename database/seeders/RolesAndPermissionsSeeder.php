<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.access',
            'tickets.view',
            'tickets.manage',
            'tickets.redact',
            'contacts.view',
            'contacts.manage',
            'contacts.anonymize',
            'companies.view',
            'companies.manage',
            'knowledge.manage',
            'knowledge.view',
            'reports.view',
            'integrations.manage',
            'audit_logs.view',
            'permissions.view',
            'permissions.manage',
            'teams.view',
            'teams.manage',
            'roles.view',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['is_system' => true]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
