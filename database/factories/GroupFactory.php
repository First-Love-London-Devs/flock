<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'group_type_id' => GroupType::factory(),
            'parent_id' => null,
            'leader_id' => null,
            'description' => null,
            'meeting_day' => 0,
            'meeting_time' => '10:00:00',
            'is_active' => true,
        ];
    }
}
