<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleProvisioner
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function syncSystemRoles(Tenant $tenant, ?User $actor = null): void
    {
        $previousTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        app()->instance('currentTenant', $tenant);

        $definitions = $this->definitions();

        $this->ensurePermissionsExist($definitions);

        foreach ($definitions as $definition) {
            $existing = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('slug', $definition['slug'])
                ->first();

            if (! $existing) {
                $this->roleService->create($definition, $actor);
                continue;
            }

            $this->roleService->update($existing, Arr::except($definition, ['slug', 'is_system']), $actor);
        }

        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();
        if (method_exists($permissionRegistrar, 'clearPermissionsCollection')) {
            $permissionRegistrar->clearPermissionsCollection();
        }

        if ($previousTenant) {
            app()->instance('currentTenant', $previousTenant);
        } else {
            app()->forgetInstance('currentTenant');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(): array
    {
        $basePermissions = [
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
            'knowledge.view',
            'knowledge.manage',
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
            'security.permission_coverage.view',
            'security.permission_coverage.manage',
        ];

        return [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full tenant administration, including roles, contacts, tickets, and knowledge base content.',
                'permissions' => $basePermissions,
                'is_system' => true,
            ],
            [
                'name' => 'Agent',
                'slug' => 'agent',
                'description' => 'Work operational tickets, manage contacts, and publish knowledge base content for the tenant.',
                'permissions' => [
                    'platform.access',
                    'portal.submit',
                    'tickets.view',
                    'tickets.manage',
                    'tickets.merge',
                    'tickets.relationships.view',
                    'tickets.relationships.manage',
                    'tickets.workflows.view',
                    'contacts.manage',
                    'companies.manage',
                    'knowledge.view',
                    'knowledge.manage',
                    'compliance.policies.view',
                    'infrastructure.redis.view',
                    'infrastructure.horizon.view',
                    'broadcast_connections.view',
                    'teams.view',
                    'security.2fa.manage',
                    'security.permission_coverage.view',
                    'ci.quality_gates.view',
                    'observability.pipelines.view',
                    'observability.stacks.view',
                    'brands.view',
                    'brand_domains.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only visibility into tickets, reports, and published knowledge base resources.',
                'permissions' => [
                    'platform.access',
                    'portal.submit',
                    'tickets.view',
                    'tickets.relationships.view',
                    'reports.view',
                    'knowledge.view',
                    'compliance.policies.view',
                    'teams.view',
                    'infrastructure.redis.view',
                    'infrastructure.horizon.view',
                    'ci.quality_gates.view',
                    'observability.pipelines.view',
                    'observability.stacks.view',
                    'brands.view',
                    'brand_domains.view',
                    'security.permission_coverage.view',
                ],
                'is_system' => true,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    protected function ensurePermissionsExist(array $definitions): void
    {
        $names = collect($definitions)
            ->pluck('permissions')
            ->flatten()
            ->unique()
            ->values();

        foreach ($names as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'is_system' => true,
            ]);
        }
    }
}
