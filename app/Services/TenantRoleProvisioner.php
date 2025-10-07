<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;

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
            'admin.access',
            'tickets.view',
            'tickets.manage',
            'tickets.redact',
            'contacts.view',
            'contacts.manage',
            'contacts.anonymize',
            'companies.view',
            'companies.manage',
            'knowledge.view',
            'knowledge.manage',
            'reports.view',
            'integrations.manage',
            'audit_logs.view',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'teams.view',
            'teams.manage',
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
                    'admin.access',
                    'tickets.view',
                    'tickets.manage',
                    'contacts.view',
                    'contacts.manage',
                    'companies.view',
                    'companies.manage',
                    'knowledge.view',
                'knowledge.manage',
                'permissions.view',
                'teams.view',
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
                    'contacts.view',
                    'companies.view',
                    'knowledge.view',
                    'teams.view',
                ],
                'is_system' => true,
            ],
        ];
    }
}
