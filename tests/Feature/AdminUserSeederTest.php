<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_one_admin_with_a_hashed_password_and_updates_it_on_rerun(): void
    {
        $seeder = new AdminUserSeeder;
        $seeder->run();
        $user = User::query()->where('email', 'trustforevermarriagebureau@gmail.com')->firstOrFail();
        $user->update(['name' => 'Changed Name']);
        $seeder->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'trustforevermarriagebureau@gmail.com',
            'name' => 'Trust Forever Marriage Bureau Admin',
        ]);
        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->hasPermissionTo('roles.view'));
        $this->assertTrue(Hash::check('tfmb@123321', $user->fresh()->password));
    }
}
