<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = Guard::getDefaultName(User::class);
        $permissions = collect(['user.view', 'user.create', 'user.update', 'user.delete'])
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guardName,
            ]));

        $adminRole = Role::query()->where('name', 'admin')->where('guard_name', $guardName)->first();
        $adminRole?->givePermissionTo($permissions);
    }
}
