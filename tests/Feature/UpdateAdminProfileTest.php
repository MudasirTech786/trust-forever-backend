<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateAdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_and_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);
        $picture = UploadedFile::fake()->createWithContent(
            'portrait.png',
            file_get_contents(base_path('../frontend/public/images/white-icon.png')),
        );

        $response = $this->post('/api/me', [
            'name' => 'Updated Name',
            'profile_picture' => $picture,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.profile_picture_url', Storage::disk('public')->url($user->fresh()->profile_picture));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
        Storage::disk('public')->assertExists($user->fresh()->profile_picture);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.profile_picture_url', $response->json('user.profile_picture_url'));
    }

    public function test_authenticated_user_can_update_name_without_a_picture(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);

        $this->postJson('/api/me', ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.profile_picture_url', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    public function test_authenticated_user_can_update_picture_without_changing_name(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Keep This Name']);
        Sanctum::actingAs($user);
        $picture = UploadedFile::fake()->createWithContent(
            'portrait.jpeg',
            file_get_contents(base_path('../frontend/public/images/background.jpeg')),
        );

        $response = $this->post('/api/me', ['profile_picture' => $picture], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('user.name', 'Keep This Name');
        $this->assertStringContainsString('profile-pictures/', $response->json('user.profile_picture_url'));
    }

    public function test_webp_profile_picture_is_accepted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $webpContents = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', true);

        $this->post('/api/me', [
            'profile_picture' => UploadedFile::fake()->createWithContent('portrait.webp', $webpContents),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertExists($user->fresh()->profile_picture);
    }

    public function test_jpg_profile_picture_is_accepted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $jpegContents = file_get_contents(base_path('../frontend/public/images/background.jpeg'));

        $this->post('/api/me', [
            'profile_picture' => UploadedFile::fake()->createWithContent('portrait.jpg', $jpegContents),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertExists($user->fresh()->profile_picture);
    }

    public function test_replacing_a_profile_picture_removes_the_previous_upload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-pictures/previous.png', 'previous image');
        $user = User::factory()->create(['profile_picture' => 'profile-pictures/previous.png']);
        Sanctum::actingAs($user);
        $picture = UploadedFile::fake()->createWithContent(
            'replacement.png',
            file_get_contents(base_path('../frontend/public/images/white-icon.png')),
        );

        $this->post('/api/me', ['profile_picture' => $picture], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing('profile-pictures/previous.png');
        Storage::disk('public')->assertExists($user->fresh()->profile_picture);
    }

    public function test_empty_name_is_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        Sanctum::actingAs($user);

        $this->postJson('/api/me', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Original Name']);
    }

    public function test_invalid_and_oversized_profile_pictures_are_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/me', [
            'profile_picture' => UploadedFile::fake()->create('not-an-image.png', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');

        $largePng = file_get_contents(base_path('../frontend/public/images/white-icon.png'))
            .str_repeat('x', 2 * 1024 * 1024);

        $this->post('/api/me', [
            'profile_picture' => UploadedFile::fake()->createWithContent('too-large.png', $largePng),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.profile_picture.0', 'Profile pictures must be 2MB or smaller.');
    }

    public function test_guest_cannot_update_a_profile(): void
    {
        $this->postJson('/api/me', ['name' => 'Changed Name'])->assertUnauthorized();
    }
}
