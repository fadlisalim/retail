<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Materialise the code-defined RBAC registry into the database.
        foreach (Rbac::PERMISSIONS as $slug => $name) {
            Permission::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'group' => explode('.', $slug)[0],
            ]);
        }

        foreach (Rbac::ROLES as $slug => $name) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'is_system' => true,
            ]);

            $permissionIds = Permission::whereIn('slug', Rbac::permissionsFor($slug))->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
