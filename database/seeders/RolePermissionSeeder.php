<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Resources that share the standard CRUD permission set. */
    private const RESOURCES = [
        'contacts', 'companies', 'deals', 'activities', 'pipelines', 'tags', 'users',
    ];

    private const ACTIONS = ['view', 'create', 'update', 'delete', 'restore'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions(Permission::all());

        $manager = Role::findOrCreate('manager', 'web');
        $manager->syncPermissions([
            ...$this->permissionsFor(['contacts', 'companies', 'deals', 'activities', 'tags']),
            ...$this->permissionsFor(['pipelines'], ['view', 'create', 'update']),
            'users.view',
            'audit-log.view',
            'records.manage-any',
        ]);

        // Reps see the whole book of business but only edit what they own —
        // enforced by CrmPolicy, which withholds `records.manage-any`.
        $rep = Role::findOrCreate('sales_rep', 'web');
        $rep->syncPermissions([
            ...$this->permissionsFor(
                ['contacts', 'companies', 'deals', 'activities'],
                ['view', 'create', 'update', 'delete'],
            ),
            'tags.view',
            'pipelines.view',
            'users.view',
        ]);

        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            ...$this->permissionsFor(self::RESOURCES, ['view']),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function allPermissions(): array
    {
        return [
            ...$this->permissionsFor(self::RESOURCES),
            'audit-log.view',
            // Cross-cutting: write to records you do not own.
            'records.manage-any',
        ];
    }

    private function permissionsFor(array $resources, ?array $actions = null): array
    {
        $actions ??= self::ACTIONS;
        $permissions = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }
}
