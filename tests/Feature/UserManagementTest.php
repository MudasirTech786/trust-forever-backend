<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_require_authentication_and_their_user_permissions(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/users')->assertForbidden();
        $target = User::factory()->create();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->putJson("/api/users/{$target->id}", [])->assertForbidden();
        $this->putJson("/api/users/{$target->id}/password", [])->assertForbidden();
        $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
        $this->getJson('/api/user-management/roles')->assertForbidden();

        $this->grant($user, ['user.create']);
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/user-management/roles')->assertOk();
        $this->postJson('/api/users', [])->assertUnprocessable();
    }

    public function test_admin_can_create_search_and_view_users_without_exposing_credentials(): void
    {
        $manager = $this->createRole('manager');
        $actor = User::factory()->create();
        $this->grant($actor, ['user.view', 'user.create', 'users.roles.assign']);
        $this->actingAs($actor, 'web');

        $response = $this->postJson('/api/users', [
            'name' => 'Matrimonial Staff',
            'email' => 'staff@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role_id' => $manager->id,
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Matrimonial Staff')
            ->assertJsonPath('user.roles.0', 'manager')
            ->assertJsonMissingPath('user.password');

        $userId = $response->json('user.id');
        $createdUser = User::findOrFail($userId);
        $this->assertNotSame('SecurePass123', $createdUser->getRawOriginal('password'));
        $this->assertTrue(Hash::check('SecurePass123', $createdUser->password));

        $this->getJson('/api/users?search=staff@example.com')
            ->assertOk()
            ->assertJsonPath('users.0.id', $userId);
        $this->getJson("/api/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('user.email', 'staff@example.com')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_role_changes_require_role_assignment_permission_and_password_reset_is_separate(): void
    {
        $originalRole = $this->createRole('receptionist');
        $newRole = $this->createRole('case-manager');
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $user->assignRole($originalRole);
        $this->grant($actor, ['user.update']);
        $this->actingAs($actor, 'web');

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Staff',
            'email' => $user->email,
            'role_id' => $newRole->id,
        ])->assertForbidden();
        $this->assertTrue($user->fresh()->hasRole('receptionist'));

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Staff',
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('user.roles.0', 'receptionist');

        $this->grant($actor, ['users.roles.assign']);
        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Staff',
            'email' => $user->email,
            'role_id' => $newRole->id,
        ])->assertOk()->assertJsonPath('user.roles.0', 'case-manager');
        $this->assertSame('Updated Staff', $user->fresh()->name);

        $this->putJson("/api/users/{$user->id}/password", [
            'password' => 'ChangedPass123',
            'password_confirmation' => 'ChangedPass123',
        ])->assertNoContent();
        $this->assertTrue(Hash::check('ChangedPass123', $user->fresh()->password));
    }

    public function test_admin_can_delete_other_users_but_not_self_primary_or_last_admin(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['user.delete', 'user.update']);
        $this->actingAs($actor, 'web');

        $this->deleteJson("/api/users/{$actor->id}")->assertUnprocessable();

        $primaryAdmin = User::factory()->create(['email' => 'trustforevermarriagebureau@gmail.com']);
        $this->deleteJson("/api/users/{$primaryAdmin->id}")->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $primaryAdmin->id]);

        $primaryRole = $this->createRole('primary-admin');
        $primaryAdmin->assignRole($primaryRole);
        $this->grant($actor, ['users.roles.assign']);
        $this->putJson("/api/users/{$primaryAdmin->id}", [
            'name' => $primaryAdmin->name,
            'email' => 'changed@example.com',
            'role_id' => $primaryRole->id,
        ])->assertUnprocessable()->assertInvalid(['email']);

        $adminRole = $this->createRole('admin');
        $lastAdmin = User::factory()->create();
        $lastAdmin->assignRole($adminRole);
        $this->deleteJson("/api/users/{$lastAdmin->id}")->assertUnprocessable();

        $staffUser = User::factory()->create();
        $this->deleteJson("/api/users/{$staffUser->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $staffUser->id]);
    }

    /** @param array<int, string> $permissionNames */
    private function grant(User $user, array $permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
        }
    }

    private function createRole(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web']);
    }
}
