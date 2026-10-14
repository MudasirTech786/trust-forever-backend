<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_401_from_current_user_endpoint(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_authenticates_with_valid_credentials_without_returning_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.password');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_accepts_the_local_ip_frontend_as_a_stateful_origin(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->withHeader('Origin', 'http://127.0.0.1:3000')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])
            ->assertInvalid(['email']);

        $this->assertGuest('web');
    }

    public function test_current_user_endpoint_returns_only_safe_user_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')->assertExactJson([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture_url' => null,
            ],
        ]);
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }
}
