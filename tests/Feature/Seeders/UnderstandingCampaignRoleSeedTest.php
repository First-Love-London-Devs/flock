<?php

namespace Tests\Feature\Seeders;

use App\Models\RoleDefinition;
use Database\Seeders\DefaultRolesSeeder;
use Tests\TestCase;

class UnderstandingCampaignRoleSeedTest extends TestCase
{
    public function test_default_roles_seeder_creates_the_understanding_campaign_role(): void
    {
        (new DefaultRolesSeeder())->run();

        $role = RoleDefinition::where('slug', 'understanding-campaign')->first();

        $this->assertNotNull($role);
        $this->assertSame('Understanding Campaign', $role->name);
        $this->assertSame(40, $role->permission_level);
        $this->assertNull($role->applies_to_group_type_id);
    }

    public function test_seeding_twice_does_not_duplicate_the_role(): void
    {
        (new DefaultRolesSeeder())->run();
        (new DefaultRolesSeeder())->run();

        $this->assertSame(1, RoleDefinition::where('slug', 'understanding-campaign')->count());
    }
}
