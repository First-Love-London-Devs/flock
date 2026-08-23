<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use App\Services\LeaderScopeService;
use Tests\TestCase;

/**
 * A top-level role that covers one country rather than everything.
 *
 * The group on a permission-level-100 role used to be ignored, so a bishop
 * over Belgium still reached all forty churches and there was no way to
 * express anything narrower. These fix both halves in place: no group still
 * means the whole tenant, a group means everything beneath it.
 */
class BishopScopeTest extends TestCase
{
    private GroupType $countryType;

    private GroupType $churchType;

    private RoleDefinition $bishop;

    private Group $belgium;

    private Group $sweden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryType = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $this->churchType = GroupType::create([
            'name' => 'Gathering Service', 'slug' => 'gathering-service', 'level' => 1,
            'tracks_attendance' => true, 'is_active' => true,
        ]);
        $this->bishop = RoleDefinition::create([
            'name' => 'Bishop', 'slug' => 'bishop', 'permission_level' => 100, 'is_active' => true,
        ]);

        $this->belgium = $this->country('Belgium', ['Antwerp', 'Ghent']);
        $this->sweden = $this->country('Sweden', ['Stockholm']);
    }

    private function country(string $name, array $churches): Group
    {
        $country = Group::create([
            'name' => $name, 'group_type_id' => $this->countryType->id, 'is_active' => true,
        ]);
        foreach ($churches as $church) {
            Group::create([
                'name' => $church, 'group_type_id' => $this->churchType->id,
                'parent_id' => $country->id, 'is_active' => true,
            ]);
        }

        return $country;
    }

    private function bishopOf(?Group $group, string $username): LeaderScopeService
    {
        $leader = Leader::factory()->create(['username' => $username]);
        LeaderRole::create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->bishop->id,
            'group_id' => $group?->id,
            'is_active' => true,
        ]);

        return (new LeaderScopeService())->setLeader($leader);
    }

    public function test_a_top_level_role_with_no_group_still_covers_the_whole_tenant(): void
    {
        // Every existing account is this shape, so it must not change.
        $scope = $this->bishopOf(null, 'TenantWide');

        $this->assertTrue($scope->isSuperAdmin());
        $this->assertSame(Group::count(), $scope->getAccessibleGroupIds()->count());
    }

    public function test_a_top_level_role_attached_to_a_country_covers_only_that_country(): void
    {
        $scope = $this->bishopOf($this->belgium, 'BelgianBishop');

        $this->assertFalse($scope->isSuperAdmin(), 'A bishop over one country is not tenant-wide.');

        $ids = $scope->getAccessibleGroupIds();
        $names = Group::whereIn('id', $ids)->pluck('name');

        $this->assertContains('Belgium', $names);
        $this->assertContains('Antwerp', $names);
        $this->assertContains('Ghent', $names);
        $this->assertNotContains('Sweden', $names);
        $this->assertNotContains('Stockholm', $names);
    }

    public function test_the_country_bishop_cannot_reach_another_country(): void
    {
        $scope = $this->bishopOf($this->belgium, 'BelgianBishop2');

        $stockholm = Group::where('name', 'Stockholm')->firstOrFail();
        $antwerp = Group::where('name', 'Antwerp')->firstOrFail();

        $this->assertTrue($scope->canAccessGroup($antwerp->id));
        $this->assertFalse($scope->canAccessGroup($stockholm->id));
    }
}
