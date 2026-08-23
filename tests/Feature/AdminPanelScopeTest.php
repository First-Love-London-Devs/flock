<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceSummaryResource;
use App\Filament\Resources\GroupResource;
use App\Filament\Resources\GroupTypeResource;
use App\Filament\Resources\LeaderResource;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\SettingResource;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use App\Models\User;
use Tests\TestCase;

/**
 * A country admin must see their own country and nothing else.
 *
 * The Filament panel filtered nothing at all before this. Every resource
 * returned whatever was in the tenant, which looked correct while a tenant
 * held one church and became a leak the moment one held forty across eight
 * countries. These tests exist so it cannot quietly go back to that.
 */
class AdminPanelScopeTest extends TestCase
{
    private Group $belgium;

    private Group $sweden;

    private Group $antwerp;

    private Group $stockholm;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Antwerp', 'group_type_id' => $churchType->id,
            'parent_id' => $this->belgium->id, 'is_active' => true,
        ]);
        $this->stockholm = Group::create([
            'name' => 'Stockholm', 'group_type_id' => $churchType->id,
            'parent_id' => $this->sweden->id, 'is_active' => true,
        ]);
    }

    private function memberIn(?Group $group, string $name): Member
    {
        $member = Member::factory()->create(['first_name' => $name]);
        if ($group) {
            $member->groups()->attach($group->id);
        }

        return $member;
    }

    private function admin(?Group $scope): User
    {
        return User::create([
            'name' => 'Admin '.($scope->name ?? 'group-wide'),
            'email' => strtolower(str_replace(' ', '', $scope->name ?? 'group')).'@example.test',
            'password' => 'password',
            'scope_group_id' => $scope?->id,
        ]);
    }

    public function test_a_country_admin_sees_only_their_own_members(): void
    {
        $this->memberIn($this->antwerp, 'Belgian');
        $this->memberIn($this->stockholm, 'Swede');

        $this->actingAs($this->admin($this->belgium));

        $names = MemberResource::getEloquentQuery()->pluck('first_name');
        $this->assertContains('Belgian', $names);
        $this->assertNotContains('Swede', $names, 'Belgium must not see Swedish members.');
    }

    public function test_a_country_admin_sees_only_their_own_groups(): void
    {
        $this->actingAs($this->admin($this->belgium));

        $names = GroupResource::getEloquentQuery()->pluck('name');
        $this->assertContains('Belgium', $names);
        $this->assertContains('Antwerp', $names);
        $this->assertNotContains('Sweden', $names);
        $this->assertNotContains('Stockholm', $names);
    }

    public function test_a_country_admin_sees_only_leaders_rooted_in_their_country(): void
    {
        $definition = RoleDefinition::create([
            'name' => 'Pastor', 'slug' => 'pastor-test',
            'permission_level' => 60, 'is_active' => true,
        ]);

        foreach ([[$this->antwerp, 'BelgianLeader'], [$this->stockholm, 'SwedishLeader']] as [$group, $name]) {
            $leader = Leader::factory()->create(['username' => $name]);
            LeaderRole::create([
                'leader_id' => $leader->id,
                'role_definition_id' => $definition->id,
                'group_id' => $group->id,
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->admin($this->belgium));

        $usernames = LeaderResource::getEloquentQuery()->pluck('username');
        $this->assertContains('BelgianLeader', $usernames);
        $this->assertNotContains('SwedishLeader', $usernames);
    }

    /**
     * Making a member a leader is two steps in the panel: create the login,
     * then give it a role. Between those two steps the leader has no role, and
     * leaders are found through the groups their roles sit on. Scoping purely
     * on that made the leader vanish the moment it was saved, so the admin who
     * had just created it could not reach step two.
     *
     * Same reasoning as the group-less members on the API side: being seen by
     * a country that turns out not to own them is recoverable, being seen by
     * nobody is not.
     */
    public function test_a_country_admin_can_still_see_a_leader_they_have_just_created(): void
    {
        $fresh = Leader::factory()->create(['username' => 'JustCreated']);

        $this->actingAs($this->admin($this->belgium));

        $usernames = LeaderResource::getEloquentQuery()->pluck('username');
        $this->assertContains(
            'JustCreated',
            $usernames,
            'A leader with no role yet must stay reachable, or step two is impossible.'
        );
    }

    /**
     * Scoping the list but not the form is worse than not scoping at all: the
     * admin picks a parent in another country, saves, and the group lands
     * outside their scope where they cannot see or fix it.
     */
    public function test_the_parent_dropdown_only_offers_groups_in_the_admins_country(): void
    {
        $this->actingAs($this->admin($this->belgium));

        $names = GroupResource::confineOptions(Group::query())->pluck('name');

        $this->assertContains('Antwerp', $names);
        $this->assertNotContains('Stockholm', $names, 'A Belgian admin must not be able to parent a group under Sweden.');
        $this->assertNotContains('Sweden', $names);
    }

    public function test_the_parent_dropdown_is_unrestricted_for_a_group_wide_admin(): void
    {
        $this->actingAs($this->admin(null));

        $names = GroupResource::confineOptions(Group::query())->pluck('name');

        $this->assertContains('Antwerp', $names);
        $this->assertContains('Stockholm', $names);
    }

    /**
     * The gap the first fix missed.
     *
     * "Make Leader" let a role be chosen without a group, so the role exists
     * and points at nothing. Such a leader fails the "role inside my country"
     * test AND fails "has no role at all", falling between the two and
     * disappearing. Six real leaders were created this way before it was spotted.
     */
    public function test_a_leader_whose_role_has_no_group_is_still_visible(): void
    {
        $definition = RoleDefinition::create([
            'name' => 'Bacenta Leader', 'slug' => 'bacenta-leader-test',
            'permission_level' => 40, 'is_active' => true,
        ]);
        $leader = Leader::factory()->create(['username' => 'RoleButNoGroup']);
        LeaderRole::create([
            'leader_id' => $leader->id,
            'role_definition_id' => $definition->id,
            'group_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin($this->belgium));

        $this->assertContains(
            'RoleButNoGroup',
            LeaderResource::getEloquentQuery()->pluck('username'),
            'A role pointing at no group must not make the leader vanish.'
        );
    }

    /**
     * Showing every unplaced leader to every country put thirteen Belgian
     * leaders into Switzerland's list. A leader is still a person, so the
     * member behind them decides who claims them.
     */
    public function test_an_unplaced_leader_belongs_to_the_country_their_member_is_in(): void
    {
        $definition = RoleDefinition::create([
            'name' => 'Bacenta Leader', 'slug' => 'bacenta-leader-claim',
            'permission_level' => 40, 'is_active' => true,
        ]);

        foreach ([[$this->antwerp, 'BelgianUnplaced'], [$this->stockholm, 'SwedishUnplaced']] as [$group, $name]) {
            $member = Member::create([
                'first_name' => $name, 'last_name' => 'X',
                'member_type' => 'member', 'is_active' => true,
            ]);
            $member->groups()->attach($group->id);

            $leader = Leader::factory()->create(['username' => $name, 'member_id' => $member->id]);
            // A role that points at nothing: the shape that caused the bug.
            LeaderRole::create([
                'leader_id' => $leader->id,
                'role_definition_id' => $definition->id,
                'group_id' => null,
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->admin($this->belgium));
        $names = LeaderResource::getEloquentQuery()->pluck('username');

        $this->assertContains('BelgianUnplaced', $names, 'Belgium should claim their own.');
        $this->assertNotContains('SwedishUnplaced', $names, 'Sweden\'s unplaced leader must not leak into Belgium.');
    }

    public function test_a_leader_nobody_can_claim_is_shown_to_everyone(): void
    {
        // No role, and the member is in no group either. Hiding them means
        // nobody can ever place them.
        $member = Member::create([
            'first_name' => 'Orphan', 'last_name' => 'Leader',
            'member_type' => 'member', 'is_active' => true,
        ]);
        Leader::factory()->create(['username' => 'NobodyClaims', 'member_id' => $member->id]);

        $this->actingAs($this->admin($this->belgium));

        $this->assertContains('NobodyClaims', LeaderResource::getEloquentQuery()->pluck('username'));
    }

    public function test_an_unscoped_admin_still_sees_everything(): void
    {
        $this->memberIn($this->antwerp, 'Belgian');
        $this->memberIn($this->stockholm, 'Swede');

        // scope_group_id null: the existing group-wide login. It must not be
        // narrowed by any of this, or adding the feature breaks the client's
        // stated requirement that the group sees all forty churches.
        $this->actingAs($this->admin(null));

        $names = MemberResource::getEloquentQuery()->pluck('first_name');
        $this->assertContains('Belgian', $names);
        $this->assertContains('Swede', $names);

        $this->assertSame(4, GroupResource::getEloquentQuery()->count());
    }

    public function test_shared_configuration_is_hidden_from_a_country_admin(): void
    {
        $this->actingAs($this->admin($this->belgium));
        $this->assertFalse(GroupTypeResource::canViewAny(), 'Group types are shared across every country.');
        $this->assertFalse(SettingResource::canViewAny());

        $this->actingAs($this->admin(null));
        $this->assertTrue(GroupTypeResource::canViewAny(), 'The group-wide admin still configures them.');
        $this->assertTrue(SettingResource::canViewAny());
    }

    /* Covers the shared trait rather than a per-resource override. The three
       resources above each define their own applyGroupScope, so without this
       the trait that five other resources rely on had no test at all — which
       a first attempt at checking these tests proved by neutering the trait
       and watching everything still pass. */
    public function test_attendance_is_scoped_through_the_shared_trait(): void
    {
        AttendanceSummary::create([
            'group_id' => $this->antwerp->id, 'date' => now()->toDateString(), 'total_attendance' => 40,
        ]);
        AttendanceSummary::create([
            'group_id' => $this->stockholm->id, 'date' => now()->toDateString(), 'total_attendance' => 25,
        ]);

        $this->actingAs($this->admin($this->belgium));
        $rows = AttendanceSummaryResource::getEloquentQuery()->get();
        $this->assertCount(1, $rows);
        $this->assertSame(40, (int) $rows->first()->total_attendance);

        $this->actingAs($this->admin(null));
        $this->assertCount(2, AttendanceSummaryResource::getEloquentQuery()->get());
    }

    /* A member attached to no group belongs to no country. They stay with
       the group-wide admin, who can still assign them, rather than showing
       up in all eight countries at once. */
    public function test_a_member_in_no_group_is_not_shown_to_a_country_admin(): void
    {
        $this->memberIn(null, 'Unassigned');

        $this->actingAs($this->admin($this->belgium));
        $this->assertNotContains('Unassigned', MemberResource::getEloquentQuery()->pluck('first_name'));

        $this->actingAs($this->admin(null));
        $this->assertContains('Unassigned', MemberResource::getEloquentQuery()->pluck('first_name'));
    }
}
