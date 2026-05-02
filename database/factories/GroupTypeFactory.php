<?php

namespace Database\Factories;

use App\Models\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupTypeFactory extends Factory
{
    protected $model = GroupType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'slug' => $this->faker->unique()->slug(2),
            'level' => 0,
            'tracks_attendance' => false,
            'icon' => 'heroicon-o-user-group',
            'color' => '#6366f1',
        ];
    }

    public function constituency(): self
    {
        return $this->state(['name' => 'Constituency', 'slug' => 'constituency', 'level' => 0, 'tracks_attendance' => false]);
    }

    public function cellGroup(): self
    {
        return $this->state(['name' => 'Cell Group', 'slug' => 'cell-group', 'level' => 2, 'tracks_attendance' => true]);
    }
}
