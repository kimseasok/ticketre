<?php

namespace App\Services;

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

        foreach ($this->definitions() as $definition) {
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
            'tickets.view',
            'tickets.manage',
            'tickets.redact',
            'contacts.manage',
            'contacts.anonymize',
            'knowledge.view',
            'knowledge.manage',
            'reports.view',
            'integrations.manage',
            'broadcast_connections.view',
            'broadcast_connections.manage',
            'audit_logs.view',
            'roles.view',
            'roles.manage',
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
                    'tickets.view',
                    'tickets.manage',
                    'contacts.manage',
                    'knowledge.view',
                    'knowledge.manage',
                    'broadcast_connections.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only visibility into tickets, reports, and published knowledge base resources.',
                'permissions' => [
                    'tickets.view',
                    'reports.view',
                    'knowledge.view',
                ],
                'is_system' => true,
            ],
        ];
    }
}
