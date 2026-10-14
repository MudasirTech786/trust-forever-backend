<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_search_applies_filters_and_returns_only_public_card_fields(): void
    {
        $match = Profile::factory()->create([
            'name' => 'Private Name',
            'gender' => 'male',
            'marital_status' => 'unmarried',
            'qualification' => 'MBBS',
            'caste' => 'Rajput',
            'city' => 'Lahore',
            'whatsapp_number' => '03001234567',
        ]);
        Profile::factory()->create([
            'gender' => 'female',
            'marital_status' => 'married',
            'qualification' => 'MBA',
            'caste' => 'Sheikh',
            'city' => 'Karachi',
        ]);

        $response = $this->getJson('/api/public/profiles/search?gender=male&marital_status=unmarried&education=professional&caste=Rajput&city=Lahore')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('profiles.0.profile_id', $match->profile_id)
            ->assertJsonPath('profiles.0.gender', 'male')
            ->assertJsonPath('profiles.0.caste', 'Rajput')
            ->assertJsonMissingPath('profiles.0.name')
            ->assertJsonMissingPath('profiles.0.whatsapp_number')
            ->assertJsonMissingPath('profiles.0.date_of_birth');

        $this->assertCount(1, $response->json('profiles'));
    }

    public function test_public_search_rejects_unknown_filter_values(): void
    {
        $this->getJson('/api/public/profiles/search?gender=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_public_search_matches_profession_and_sect_aliases_for_both_genders(): void
    {
        foreach ([
            ['gender' => 'male', 'job' => 'Dr.', 'sect' => 'Ahle Tashi'],
            ['gender' => 'female', 'business' => 'Medical Doctor', 'sect' => 'Ahl-e Tashee'],
            ['gender' => 'male', 'job' => 'Software Engineer', 'sect' => 'Shia'],
            ['gender' => 'female', 'job' => 'Civil-Engineer', 'sect' => 'SHIYA.'],
        ] as $attributes) {
            Profile::factory()->create($attributes);
        }

        foreach (['Doctor', 'Dr'] as $profession) {
            foreach (['male', 'female'] as $gender) {
                $response = $this->getJson('/api/public/profiles/search?'.http_build_query([
                    'gender' => $gender,
                    'profession' => $profession,
                ]))->assertOk();

                $this->assertCount(1, $response->json('profiles'));
                $this->assertSame($gender, $response->json('profiles.0.gender'));
            }
        }

        foreach (['Engineer'] as $profession) {
            foreach (['male', 'female'] as $gender) {
                $response = $this->getJson('/api/public/profiles/search?'.http_build_query([
                    'gender' => $gender,
                    'profession' => $profession,
                ]))->assertOk();

                $this->assertCount(1, $response->json('profiles'));
                $this->assertSame($gender, $response->json('profiles.0.gender'));
            }
        }

        foreach (['Ahle Tashi', 'Shia', 'Shiya'] as $sect) {
            foreach (['male', 'female'] as $gender) {
                $response = $this->getJson('/api/public/profiles/search?'.http_build_query([
                    'gender' => $gender,
                    'sect' => $sect,
                ]))->assertOk();

                $this->assertCount(1, $response->json('profiles'));
                $this->assertSame($gender, $response->json('profiles.0.gender'));
                $this->assertNotEmpty($response->json('profiles.0.sect'));
            }
        }

        $this->getJson('/api/public/profiles/search?'.http_build_query([
            'gender' => 'female', 'profession' => 'Engineer', 'sect' => 'Shiya',
        ]))->assertOk()->assertJsonPath('total', 1);

        $this->getJson('/api/public/profiles/search?'.http_build_query([
            'gender' => 'All', 'profession' => 'Doctor',
        ]))->assertOk()->assertJsonPath('total', 2);

        $this->getJson('/api/public/profiles/search?'.http_build_query([
            'gender' => 'all', 'profession' => 'Accountant',
        ]))->assertOk()->assertJsonPath('total', 0);

        $this->getJson('/api/public/profiles/search?'.http_build_query([
            'gender' => 'all', 'sect' => 'Sunni',
        ]))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_public_search_filters_categories_and_paginates_results(): void
    {
        $profiles = collect(range(1, 3))->map(fn (int $index): Profile => Profile::factory()->create([
            'gender' => 'male',
            'city' => 'Lahore',
            'qualification' => 'MBBS',
        ]));

        $this->getJson('/api/public/profiles/search?gender=male&city=Lahore&category=doctors&per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('profiles.0.profile_id', $profiles[0]->profile_id)
            ->assertJsonMissingPath('profiles.1');
    }

    public function test_public_profile_detail_uses_only_public_card_fields(): void
    {
        $profile = Profile::factory()->create([
            'name' => 'Private Name',
            'whatsapp_number' => '03001234567',
            'father_name' => 'Private Contact',
        ]);

        $this->getJson('/api/public/profiles/'.$profile->profile_id)
            ->assertOk()
            ->assertJsonPath('profile.profile_id', $profile->profile_id)
            ->assertJsonMissingPath('profile.name')
            ->assertJsonMissingPath('profile.whatsapp_number')
            ->assertJsonMissingPath('profile.father_name');
    }
}
