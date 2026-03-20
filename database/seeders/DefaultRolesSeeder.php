<?php

namespace Database\Seeders;

use App\Models\GroupType;
use App\Models\RoleDefinition;
use Illuminate\Database\Seeder;

class DefaultRolesSeeder extends Seeder
{
    public function run(): void
    {
        $zoneType = GroupType::where('slug', 'zone')->first();
        $districtType = GroupType::where('slug', 'district')->first();
        $cellType = GroupType::where('slug', 'cell-group')->first();

        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'permission_level' => 100, 'applies_to_group_type_id' => null],
            ['name' => 'Zone Overseer', 'slug' => 'zone-overseer', 'permission_level' => 80, 'applies_to_group_type_id' => $zoneType?->id],
            ['name' => 'District Pastor', 'slug' => 'district-pastor', 'permission_level' => 60, 'applies_to_group_type_id' => $districtType?->id],
            ['name' => 'Cell Leader', 'slug' => 'cell-leader', 'permission_level' => 40, 'applies_to_group_type_id' => $cellType?->id],
        ];

        foreach ($roles as $role) {
            RoleDefinition::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
