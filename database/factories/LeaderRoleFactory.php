<?php

namespace Database\Factories;

use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderRoleFactory extends Factory
{
    protected $model = LeaderRole::class;

    public function definition(): array
    {
        return [
            'leader_id' => Leader::factory(),
            'role_definition_id' => RoleDefinition::factory(),
            'group_id' => null,
            'assigned_at' => now(),
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
