<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = Guard::getDefaultName(User::class);
        $permissionNames = [
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            'users.roles.view',
            'users.roles.assign',
            'users.roles.remove',
            'profiles.view',
            'profiles.create',
            'profiles.edit',
            'profiles.delete',
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
        ];

        $permissions = collect($permissionNames)
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guardName,
            ]));

        $role = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guardName,
        ]);
        $role->syncPermissions($permissions);
    }
}
