<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use App\Services\DomainScope;
use App\Services\LeaderScopeService;
use Tests\TestCase;

/**
 * A country subdomain must be able to narrow what a leader sees and must
 * never be able to widen it.
 *
 * This is the whole safety argument for giving each country its own
 * address: the domain is a string anybody can type, so if it could grant
 * access, typing another country's name would be a way in. These tests
 * exist so that stays true when someone later changes LeaderScopeService.
 */
class DomainScopeTest extends TestCase
{
    // Tests\TestCase already applies RefreshDatabase and overrides its
    // refresh methods for tenancy; re-declaring the trait here is a fatal
    // signature clash.

    private Group $belgium;
    private Group $sweden;
    private Group $antwerp;
    private Group $stockholm;

    protected function setUp(): void
    {
        parent::setUp();
        DomainScope::forget();

        $countryType = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $churchType = GroupType::create([
            'name' => 'Church', 'slug' => 'church', 'level' => 1,
            'tracks_attendance' => true, 'is_active' => true,
        ]);

        $this->belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->sweden = Group::create(['name' => 'Sweden', 'group_type_id' => $countryType->id, 'is_active' => true]);

        $this->antwerp = Group::create([
            'name' => 'Go Church Antwerp', 'group_type_id' => $churchType->id,
            'parent_id' => $this->belgium->id, 'is_active' => true,
        ]);
        $this->stockholm = Group::create([
            'name' => 'Stockholm', 'group_type_id' => $churchType->id,
            'parent_id' => $this->sweden->id, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DomainScope::forget();
        parent::tearDown();
    }

    private function leaderScopedTo(Group $group, int $permissionLevel = 50): Leader
    {
        $leader = Leader::factory()->create();
        $definition = RoleDefinition::create([
            'name' => 'Scoped '.$group->name,
            'slug' => 'scoped-'.$group->id.'-'.$permissionLevel,
            'permission_level' => $permissionLevel,
            'is_active' => true,
        ]);
        LeaderRole::create([
            'leader_id' => $leader->id,
            'role_definition_id' => $definition->id,
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        return $leader;
    }

    private function scopeFor(Leader $leader): LeaderScopeService
    {
        return app(LeaderScopeService::class)->setLeader($leader);
    }

    public function test_without_a_domain_scope_a_leader_sees_their_whole_subtree(): void
    {
        $scope = $this->scopeFor($this->leaderScopedTo($this->belgium));

        $this->assertTrue($scope->canAccessGroup($this->belgium->id));
        $this->assertTrue($scope->canAccessGroup($this->antwerp->id));
        $this->assertFalse($scope->canAccessGroup($this->stockholm->id));
    }

    public function test_a_domain_narrows_a_group_wide_leader_to_one_country(): void
    {
        // Someone whose role covers everything, arriving through Belgium.
        $groupWide = $this->leaderScopedTo($this->belgium);
        LeaderRole::where('leader_id', $groupWide->id)->update(['group_id' => $this->sweden->id]);

        $this->withDomain('belgium.church-stack.com', 'Belgium');

        $scope = $this->scopeFor($groupWide);
        $this->assertFalse(
            $scope->canAccessGroup($this->stockholm->id),
            'Coming in through Belgium must not show Swedish groups.',
        );
    }

    public function test_a_domain_cannot_widen_what_a_leader_can_reach(): void
    {
        // A Belgian leader typing another country's address gets nothing
        // extra. This is the property that makes country subdomains safe.
        $belgian = $this->leaderScopedTo($this->antwerp);

        $this->withDomain('sweden.church-stack.com', 'Sweden');

        $scope = $this->scopeFor($belgian);
        $this->assertFalse($scope->canAccessGroup($this->stockholm->id));
        $this->assertFalse($scope->canAccessGroup($this->sweden->id));
        $this->assertTrue(
            $scope->getAccessibleGroupIds()->isEmpty(),
            'Their own groups are outside the domain, so the intersection is empty.',
        );
    }

    public function test_an_unknown_scope_name_narrows_nothing(): void
    {
        // A renamed or deleted group must not lock everyone out.
        $this->withDomain('ghost.church-stack.com', 'Atlantis');

        $scope = $this->scopeFor($this->leaderScopedTo($this->belgium));
        $this->assertTrue($scope->canAccessGroup($this->antwerp->id));
    }

    /**
     * Confine as though the request had arrived on that country's domain.
     *
     * These tests run against a tenant-only database, so the central
     * `domains` table is not there to insert a row into. What matters here
     * is the intersection in LeaderScopeService rather than the host
     * lookup, which is a single where() on a table.
     */
    private function withDomain(string $host, ?string $scopeGroupName): void
    {
        DomainScope::fake($scopeGroupName);
    }
}
