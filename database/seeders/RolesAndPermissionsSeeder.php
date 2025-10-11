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
            'portal.sessions.view',
            'portal.sessions.manage',
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
            'sla.policies.view',
            'sla.policies.manage',
            'brands.view',
            'brands.manage',
            'brand_assets.view',
            'brand_assets.manage',
            'brand_domains.view',
            'brand_domains.manage',
            'email.dispatches.view',
            'email.dispatches.manage',
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
            'security.permission_coverage.view',
            'security.permission_coverage.manage',
            'security.rbac_gaps.view',
            'security.rbac_gaps.manage',
        ];

        $portalGuardPermissions = [
            'portal.access',
            'portal.tickets.view',
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

        foreach ($portalGuardPermissions as $permission) {
            Permission::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => null,
                'name' => $permission,
                'guard_name' => 'portal',
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

        $portalRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => 'PortalUser',
            'slug' => 'portal-user',
            'guard_name' => 'portal',
            'tenant_id' => null,
        ], [
            'description' => 'Portal customer with ticket visibility.',
            'is_system' => true,
        ]);

        $portalRole->syncPermissions($portalGuardPermissions);

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

            foreach ($portalGuardPermissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'portal',
                ], [
                    'description' => null,
                    'is_system' => true,
                ]);
            }

            Role::withoutGlobalScopes()->firstOrCreate([
                'name' => 'PortalUser',
                'slug' => 'portal-user',
                'guard_name' => 'portal',
                'tenant_id' => $tenant->getKey(),
            ], [
                'description' => 'Portal customer with ticket visibility.',
                'is_system' => true,
            ])->syncPermissions($portalGuardPermissions);

            $provisioner->syncSystemRoles($tenant);

            app()->forgetInstance('currentTenant');
        });
    }
}
