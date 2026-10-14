<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTrendingProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_trending_feed_returns_only_ten_latest_profiles_and_omits_private_fields(): void
    {
        $timestamp = now()->subHour();
        $createdProfiles = collect(range(1, 12))->map(fn (): Profile => Profile::factory()->create([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'gender' => 'male',
            'marital_status' => 'unmarried',
            'date_of_birth' => '1995-05-12',
            'city' => 'Lahore',
            'whatsapp_number' => '03001234567',
        ]));

        $expectedIds = $createdProfiles
            ->sortByDesc('id')
            ->take(10)
            ->pluck('profile_id')
            ->values()
            ->all();

        $response = $this->getJson('/api/public/trending-profiles')->assertOk();
        $actualProfiles = $response->json('profiles');

        $this->assertCount(10, $actualProfiles);
        $this->assertSame($expectedIds, collect($actualProfiles)->pluck('profile_id')->all());
        $response->assertJsonStructure(['profiles' => [[
            'profile_id',
            'gender',
            'marital_status',
            'age',
            'height',
            'caste',
            'qualification',
            'educational_institution',
            'job',
            'business',
            'city',
        ]]]);
        $response
            ->assertJsonMissingPath('profiles.0.name')
            ->assertJsonMissingPath('profiles.0.date_of_birth')
            ->assertJsonMissingPath('profiles.0.profile_picture_url')
            ->assertJsonMissingPath('profiles.0.whatsapp_number')
            ->assertJsonMissingPath('profiles.0.father_whatsapp');
    }

    public function test_public_trending_feed_returns_all_profiles_when_fewer_than_ten_exist(): void
    {
        Profile::factory()->count(3)->create();

        $this->getJson('/api/public/trending-profiles')
            ->assertOk()
            ->assertJsonCount(3, 'profiles');
    }
}
