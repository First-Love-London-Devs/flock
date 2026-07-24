<?php

namespace Tests\Feature\Seeders;

use App\Models\RoleDefinition;
use Database\Seeders\DefaultRolesSeeder;
use Tests\TestCase;

class UnderstandingCampaignRoleSeedTest extends TestCase
{
    public function test_default_roles_seeder_creates_the_understanding_campaign_role(): void
    {
        // The tenant migration already seeds this role before the test body
        // runs. Delete it first so firstOrCreate() inside the seeder is
        // forced to genuinely insert using the seeder's own attributes,
        // rather than finding the migration's row and returning it untouched.
        RoleDefinition::where('slug', 'understanding-campaign')->delete();

        (new DefaultRolesSeeder())->run();

        $role = RoleDefinition::where('slug', 'understanding-campaign')->first();

        $this->assertNotNull($role);
        $this->assertSame('Understanding Campaign', $role->name);
        $this->assertSame(40, $role->permission_level);
        $this->assertNull($role->applies_to_group_type_id);
    }

    public function test_seeding_twice_does_not_duplicate_the_role(): void
    {
        // Start from a clean slate so this proves the seeder itself is
        // idempotent, not that the migration already put a row in place.
        RoleDefinition::where('slug', 'understanding-campaign')->delete();

        (new DefaultRolesSeeder())->run();
        (new DefaultRolesSeeder())->run();

        $this->assertSame(1, RoleDefinition::where('slug', 'understanding-campaign')->count());
    }

    public function test_migration_up_is_idempotent_for_the_understanding_campaign_role(): void
    {
        $migration = require database_path('migrations/tenant/2026_07_24_120000_seed_understanding_campaign_role.php');

        RoleDefinition::where('slug', 'understanding-campaign')->delete();

        $migration->up();
        $migration->up();

        $this->assertSame(1, RoleDefinition::where('slug', 'understanding-campaign')->count());
    }
}
