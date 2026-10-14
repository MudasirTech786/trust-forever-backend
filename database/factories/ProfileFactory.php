<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_picture' => null,
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['female', 'male']),
            'marital_status' => 'unmarried',
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-20 years')->format('Y-m-d'),
            'height' => '5 ft 5 in',
            'sect' => fake()->randomElement(['Sunni', 'Shia']),
            'caste' => fake()->lastName(),
            'language' => 'Urdu',
            'qualification' => fake()->randomElement(['BS', 'MBA', 'BDS']),
            'educational_institution' => fake()->city().' University',
            'employed' => fake()->boolean(),
            'father_details' => fake()->sentence(),
            'mother_details' => fake()->sentence(),
            'house_size' => '1 kanal',
            'society' => fake()->city(),
            'city' => fake()->city(),
            'sisters_count' => 1,
            'sisters_details' => null,
            'married_sisters_count' => 0,
            'brothers_count' => 1,
            'brothers_details' => null,
            'married_brothers_count' => 0,
            'partner_requirements' => null,
            'father_whatsapp' => null,
            'mother_whatsapp' => null,
            'created_by' => User::factory(),
        ];
    }
}
