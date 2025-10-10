<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'platform.access',
            'portal.submit',
            'observability.pipelines.view',
            'observability.pipelines.manage',
            'observability.stacks.view',
            'observability.stacks.manage',
            'ci.quality_gates.view',
            'ci.quality_gates.manage',
            'tickets.view',
            'tickets.manage',
            'tickets.merge',
            'tickets.redact',
            'tickets.workflows.view',
            'tickets.workflows.manage',
            'tickets.relationships.view',
            'tickets.relationships.manage',
            'contacts.manage',
            'contacts.anonymize',
            'companies.manage',
            'compliance.policies.view',
            'compliance.policies.manage',
            'knowledge.manage',
            'knowledge.view',
            'reports.view',
            'integrations.manage',
            'brands.view',
            'brands.manage',
            'brand_domains.view',
            'brand_domains.manage',
            'infrastructure.redis.view',
            'infrastructure.redis.manage',
            'infrastructure.horizon.view',
            'infrastructure.horizon.manage',
            'broadcast_connections.view',
            'broadcast_connections.manage',
            'audit_logs.view',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'teams.view',
            'teams.manage',
            'security.2fa.manage',
            'security.2fa.review',
        ];

        foreach ($permissions as $permission) {
            Permission::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => null,
                'name' => $permission,
                'guard_name' => 'web',
            ], [
                'description' => null,
                'is_system' => true,
            ]);
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

        Tenant::query()->each(function (Tenant $tenant) use ($permissions, $provisioner): void {
            app()->instance('currentTenant', $tenant);
            app()->forgetInstance('currentBrand');

            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ], [
                    'description' => null,
                    'is_system' => true,
                ]);
            }

            $provisioner->syncSystemRoles($tenant);

            app()->forgetInstance('currentTenant');
        });
    }
}
