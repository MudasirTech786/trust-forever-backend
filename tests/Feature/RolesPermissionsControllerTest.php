<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_roles(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_manage_roles(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/roles')->assertForbidden();
    }

    public function test_existing_admin_can_login_and_access_role_management(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/login', [
                'email' => 'trustforevermarriagebureau@gmail.com',
                'password' => 'tfmb@123321',
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_admin_can_manage_roles_permissions_and_user_assignments(): void
    {
        $manager = User::factory()->create();
        foreach ([
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete',
            'users.roles.view', 'users.roles.assign', 'users.roles.remove',
        ] as $permissionName) {
            $manager->givePermissionTo(Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]));
        }
        $this->actingAs($manager, 'web');

        $permissionResponse = $this->postJson('/api/permissions', ['name' => 'profiles.view'])
            ->assertCreated()
            ->assertJsonPath('permission.name', 'profiles.view');
        $permissionId = $permissionResponse->json('permission.id');

        $roleResponse = $this->postJson('/api/roles', ['name' => 'profile-editor'])
            ->assertCreated()
            ->assertJsonPath('role.name', 'profile-editor');
        $roleId = $roleResponse->json('role.id');
        $this->assertDatabaseHas('roles', ['id' => $roleId, 'name' => 'profile-editor', 'guard_name' => 'web']);
        $this->getJson("/api/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('role.name', 'profile-editor');
        $this->putJson("/api/roles/{$roleId}", ['name' => 'profile-curator'])
            ->assertOk()
            ->assertJsonPath('role.name', 'profile-curator');

        $this->putJson("/api/roles/{$roleId}/permissions", ['permissions' => [$permissionId]])
            ->assertOk()
            ->assertJsonPath('role.permissions.0.name', 'profiles.view');

        $user = User::factory()->create();
        $this->postJson("/api/users/{$user->id}/roles", ['roles' => ['profile-curator']])
            ->assertOk()
            ->assertJsonPath('roles.0', 'profile-curator');
        $this->putJson("/api/users/{$user->id}/roles", ['roles' => ['profile-curator']])
            ->assertOk()
            ->assertJsonPath('roles.0', 'profile-curator');
        $this->getJson("/api/users/{$user->id}/roles")
            ->assertOk()
            ->assertJsonPath('roles.0', 'profile-curator');
        $this->getJson("/api/users/{$user->id}/permissions")
            ->assertOk()
            ->assertJsonPath('permissions.0', 'profiles.view');
        $this->assertTrue($user->fresh()->hasRole('profile-curator'));
        $this->assertTrue($user->fresh()->hasPermissionTo('profiles.view'));

        $this->putJson("/api/roles/{$roleId}/permissions", ['permissions' => []])
            ->assertOk()
            ->assertJsonPath('role.permissions', []);
        $this->assertTrue($user->fresh()->hasRole('profile-curator'));
        $this->assertFalse($user->fresh()->hasPermissionTo('profiles.view'));

        $this->deleteJson("/api/users/{$user->id}/roles", ['roles' => ['profile-curator']])
            ->assertOk();
        $this->assertFalse($user->fresh()->hasRole('profile-curator'));
        $this->assertFalse($user->fresh()->hasPermissionTo('profiles.view'));

        $this->putJson("/api/permissions/{$permissionId}", ['name' => 'profiles.read'])
            ->assertOk()
            ->assertJsonPath('permission.name', 'profiles.read');
        $this->deleteJson("/api/roles/{$roleId}")->assertNoContent();
        $this->deleteJson("/api/permissions/{$permissionId}")->assertNoContent();
    }

    public function test_administrator_role_cannot_be_deleted(): void
    {
        $this->seed(AdminUserSeeder::class);

        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findByName('roles.delete', 'web'));
        $this->actingAs($manager, 'web');

        $administrator = Role::findByName('admin', 'web');

        $this->deleteJson("/api/roles/{$administrator->id}")
            ->assertUnprocessable()
            ->assertInvalid(['role']);

        $this->assertDatabaseHas('roles', ['id' => $administrator->id, 'name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_permission_endpoints_validate_duplicate_names(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create([
            'name' => 'permissions.create',
            'guard_name' => 'web',
        ]));
        $this->assertDatabaseHas('permissions', ['name' => 'permissions.create', 'guard_name' => 'web']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/permissions', ['name' => 'permissions.create'])
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }
}
