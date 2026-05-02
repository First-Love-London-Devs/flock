<?php

namespace Tests\Feature\Seeders;

use App\Models\GroupType;
use App\Models\RoleDefinition;
use Database\Seeders\DefaultGroupTypesSeeder;
use Database\Seeders\DefaultRolesSeeder;
use Tests\TestCase;

class DefaultRolesSeederTest extends TestCase
{
    public function test_seeders_create_constituency_type_and_governance_roles(): void
    {
        $this->seed(DefaultGroupTypesSeeder::class);
        $this->seed(DefaultRolesSeeder::class);

        $constituency = GroupType::where('slug', 'constituency')->first();
        $this->assertNotNull($constituency);
        $this->assertSame(0, $constituency->level);
        $this->assertFalse((bool) $constituency->tracks_attendance);

        $bishop = RoleDefinition::where('slug', 'bishop')->first();
        $this->assertNotNull($bishop);
        $this->assertSame(90, $bishop->permission_level);
        $this->assertNull($bishop->applies_to_group_type_id);

        $governor = RoleDefinition::where('slug', 'governor')->first();
        $this->assertNotNull($governor);
        $this->assertSame(70, $governor->permission_level);
        $this->assertSame($constituency->id, $governor->applies_to_group_type_id);
    }

    public function test_existing_roles_remain_intact(): void
    {
        $this->seed(DefaultGroupTypesSeeder::class);
        $this->seed(DefaultRolesSeeder::class);

        foreach (['super-admin', 'zone-overseer', 'district-pastor', 'cell-leader'] as $slug) {
            $this->assertNotNull(RoleDefinition::where('slug', $slug)->first(), "missing $slug");
        }
    }
}
