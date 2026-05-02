<?php

namespace Database\Factories;

use App\Models\RoleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleDefinitionFactory extends Factory
{
    protected $model = RoleDefinition::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            'slug' => $this->faker->unique()->slug(2),
            'permission_level' => 50,
            'applies_to_group_type_id' => null,
            'is_active' => true,
        ];
    }

    public function bishop(): self
    {
        return $this->state(['name' => 'Bishop', 'slug' => 'bishop', 'permission_level' => 90, 'applies_to_group_type_id' => null]);
    }

    public function governor(): self
    {
        return $this->state(['name' => 'Governor', 'slug' => 'governor', 'permission_level' => 70]);
    }
}
