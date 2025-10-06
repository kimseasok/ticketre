<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'tickets.view',
            'tickets.manage',
            'contacts.manage',
            'knowledge.manage',
            'knowledge.view',
            'reports.view',
            'integrations.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roleDefinitions = [
            'SuperAdmin' => $permissions,
            'Admin' => $permissions,
            'Agent' => ['tickets.view', 'tickets.manage', 'contacts.manage', 'knowledge.view'],
            'Viewer' => ['tickets.view', 'reports.view', 'knowledge.view'],
        ];

        foreach ($roleDefinitions as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
