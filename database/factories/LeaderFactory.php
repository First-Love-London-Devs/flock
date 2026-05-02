<?php

namespace Database\Factories;

use App\Models\Leader;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderFactory extends Factory
{
    protected $model = Leader::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'username' => $this->faker->unique()->userName(),
            'password' => 'password',
            'is_active' => true,
        ];
    }
}
