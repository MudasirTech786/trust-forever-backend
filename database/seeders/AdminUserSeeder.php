<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::updateOrCreate(
            ['email' => 'trustforevermarriagebureau@gmail.com'],
            [
                'name' => 'Trust Forever Marriage Bureau Admin',
                'password' => Hash::make('tfmb@123321'),
            ],
        );

        $admin->syncRoles('admin');
    }
}
