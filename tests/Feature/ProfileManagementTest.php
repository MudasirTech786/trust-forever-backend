<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_routes_require_authentication_and_permissions(): void
    {
        $this->getJson('/api/profiles')->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'web')
            ->getJson('/api/profiles')
            ->assertForbidden();
    }

    public function test_admin_can_create_search_update_and_delete_profiles_with_picture_cleanup(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        foreach (['profiles.view', 'profiles.create', 'profiles.edit', 'profiles.delete'] as $permissionName) {
            $user->givePermissionTo(Permission::create(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $this->actingAs($user, 'web');

        $payload = $this->profilePayload();
        $payload['profile_picture'] = $this->fakePng('portrait.png');
        $response = $this->post('/api/profiles', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('profile.name', 'Sample Profile')
            ->assertJsonPath('profile.gender', 'female');
        $profileId = $response->json('profile.id');
        $this->assertSame("TFMB-{$profileId}", $response->json('profile.profile_id'));
        $originalPicture = Profile::findOrFail($profileId)->profile_picture;
        Storage::disk('public')->assertExists($originalPicture);

        $this->getJson('/api/profiles?search=sample&city=Lahore')
            ->assertOk()
            ->assertJsonPath('profiles.0.id', $profileId);
        $this->getJson("/api/profiles?search=TFMB-{$profileId}")
            ->assertOk()
            ->assertJsonPath('profiles.0.profile_id', "TFMB-{$profileId}");
        $this->getJson("/api/profiles/{$profileId}")
            ->assertOk()
            ->assertJsonPath('profile.city', 'Lahore');

        $replacement = $this->fakePng('replacement.png');
        $this->post("/api/profiles/{$profileId}", [
            '_method' => 'PUT',
            'name' => 'Updated Profile',
            'profile_picture' => $replacement,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('profile.name', 'Updated Profile');
        $updatedPicture = Profile::findOrFail($profileId)->profile_picture;
        $this->assertNotSame($originalPicture, $updatedPicture);
        Storage::disk('public')->assertMissing($originalPicture);
        Storage::disk('public')->assertExists($updatedPicture);

        $this->deleteJson("/api/profiles/{$profileId}")->assertNoContent();
        $this->assertDatabaseMissing('profiles', ['id' => $profileId]);
        Storage::disk('public')->assertMissing($updatedPicture);
    }

    public function test_profile_picture_is_optional_and_whatsapp_remains_validated(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'profiles.create', 'guard_name' => 'web']));
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/profiles', $this->profilePayload())->assertCreated();
        $profileId = $response->json('profile.id');
        $this->assertStringEndsWith('/storage/matrimonial-profiles/avatar.jpg', $response->json('profile.profile_picture_url'));
        $this->assertDatabaseHas('profiles', ['id' => $profileId, 'profile_picture' => null]);

        $payload = $this->profilePayload();
        $payload['profile_picture'] = $this->fakePng('portrait.png');
        $payload['father_whatsapp'] = 'not-a-phone';
        $this->post('/api/profiles', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertInvalid(['father_whatsapp']);
    }

    public function test_profiles_can_be_created_with_only_name_age_or_no_details(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'profiles.create', 'guard_name' => 'web']));
        $this->actingAs($user, 'web');

        $this->postJson('/api/profiles', ['name' => 'Name only'])
            ->assertCreated()->assertJsonPath('profile.name', 'Name only')->assertJsonPath('profile.age', null);
        $this->postJson('/api/profiles', ['age' => 34])
            ->assertCreated()->assertJsonPath('profile.age', 34);
        $this->postJson('/api/profiles', ['age' => 27])
            ->assertCreated()->assertJsonPath('profile.age', 27);
        $this->postJson('/api/profiles', [])
            ->assertCreated()->assertJsonPath('profile.name', null)->assertJsonPath('profile.age', null);
        $this->postJson('/api/profiles', ['age' => 121])->assertUnprocessable()->assertInvalid(['age']);
    }

    public function test_sibling_counts_are_optional_nullable_and_validated_when_provided(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'profiles.create', 'guard_name' => 'web']));
        $this->actingAs($user, 'web');

        $empty = $this->postJson('/api/profiles', [
            'sisters_count' => '  ', 'brothers_count' => '',
            'married_sisters_count' => '', 'married_brothers_count' => '',
        ])->assertCreated()->json('profile');
        $this->assertNull($empty['sisters_count']);
        $this->assertNull($empty['brothers_count']);

        $this->postJson('/api/profiles', ['sisters_count' => '2'])->assertCreated()
            ->assertJsonPath('profile.sisters_count', 2)->assertJsonPath('profile.brothers_count', null);
        $this->postJson('/api/profiles', ['brothers_count' => '3'])->assertCreated()
            ->assertJsonPath('profile.brothers_count', 3)->assertJsonPath('profile.sisters_count', null);
        $this->postJson('/api/profiles', [
            'sisters_count' => '2', 'brothers_count' => '3',
            'married_sisters_count' => '1', 'married_brothers_count' => '2',
        ])->assertCreated()->assertJsonPath('profile.sisters_count', 2)->assertJsonPath('profile.brothers_count', 3);
        $this->postJson('/api/profiles', ['sisters_count' => 'several'])->assertUnprocessable()->assertInvalid(['sisters_count']);
        $this->postJson('/api/profiles', ['brothers_count' => 'many'])->assertUnprocessable()->assertInvalid(['brothers_count']);
    }

    public function test_profile_and_dashboard_return_the_uploaded_and_default_picture_urls(): void
    {
        $this->assertFileExists(storage_path('app/public/matrimonial-profiles/avatar.jpg'));
        Storage::fake('public');
        $user = User::factory()->create();
        foreach (['profiles.view', 'profiles.create'] as $permissionName) {
            $user->givePermissionTo(Permission::create(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $this->actingAs($user, 'web');

        $photoResponse = $this->post('/api/profiles', [
            ...$this->profilePayload(),
            'profile_picture' => $this->fakePng('portrait.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $photoProfile = Profile::findOrFail($photoResponse->json('profile.id'));
        $photoUrl = Storage::disk('public')->url($photoProfile->profile_picture);
        $this->assertSame($photoUrl, $photoResponse->json('profile.profile_picture_url'));

        $defaultResponse = $this->postJson('/api/profiles', $this->profilePayload())->assertCreated();
        $defaultProfile = Profile::findOrFail($defaultResponse->json('profile.id'));
        $defaultUrl = Storage::disk('public')->url('matrimonial-profiles/avatar.jpg');
        $this->assertNull($defaultProfile->profile_picture);
        $this->assertSame($defaultUrl, $defaultResponse->json('profile.profile_picture_url'));

        $dashboard = $this->getJson('/api/dashboard/profile-summary')->assertOk();
        $recentProfiles = collect($dashboard->json('recent_profiles'))->keyBy('id');
        $this->assertSame($photoUrl, $recentProfiles[$photoProfile->id]['profile_picture_url']);
        $this->assertSame($defaultUrl, $recentProfiles[$defaultProfile->id]['profile_picture_url']);
    }

    /** @return array<string, mixed> */
    private function profilePayload(): array
    {
        return [
            'name' => 'Sample Profile',
            'gender' => 'female',
            'marital_status' => 'unmarried',
            'date_of_birth' => '1998-05-12',
            'height' => '5 ft 5 in',
            'sect' => 'Sunni',
            'caste' => 'Rajput',
            'qualification' => 'BS',
            'city' => 'Lahore',
            'sisters_count' => 1,
            'married_sisters_count' => 0,
            'brothers_count' => 2,
            'married_brothers_count' => 1,
            'father_whatsapp' => '03001234567',
        ];
    }

    private function fakePng(string $name): UploadedFile
    {
        $imageContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $imageContents);
    }
}
