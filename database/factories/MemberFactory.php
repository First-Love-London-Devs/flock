<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'member_type' => 'member',
            'is_active' => true,
            'profile_completed' => true,
            'member_since' => now()->subYears(2),
        ];
    }
}
