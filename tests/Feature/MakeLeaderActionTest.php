<?php

namespace Tests\Feature;

use App\Filament\Resources\MemberResource;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The "Make Leader" action on the members table.
 *
 * It hands out a working login, so what matters is that the password is
 * hashed rather than stored as typed, that a generated username never
 * collides with one already in use, and that a role is only attached when one
 * was actually chosen.
 */
class MakeLeaderActionTest extends TestCase
{
    private function member(string $first, string $last): Member
    {
        return Member::create([
            'first_name' => $first, 'last_name' => $last,
            'member_type' => 'member', 'is_active' => true,
        ]);
    }

    public function test_it_suggests_a_username_in_the_house_style(): void
    {
        $this->assertSame(
            'aaron.kokodoko',
            MemberResource::suggestUsername($this->member('Aaron', 'Kokodoko'))
        );
    }

    public function test_it_strips_spaces_and_accents_the_way_the_existing_logins_do(): void
    {
        // Real examples from the tenant: "Chodar Rezaie" is one word in the
        // username, and accented names are not left with stray characters.
        $this->assertSame(
            'maykel.chodarrezaie',
            MemberResource::suggestUsername($this->member('Maykel', 'Chodar Rezaie'))
        );
        $this->assertSame(
            'chloe.mateta',
            MemberResource::suggestUsername($this->member('Chloë', 'Mateta'))
        );
    }

    public function test_a_suggested_username_never_collides_with_an_existing_one(): void
    {
        $first = $this->member('Aaron', 'Kokodoko');
        MemberResource::promoteToLeader($first, [
            'username' => MemberResource::suggestUsername($first),
            'password' => 'whatever',
        ]);

        $namesake = $this->member('Aaron', 'Kokodoko');

        $this->assertSame('aaron.kokodoko2', MemberResource::suggestUsername($namesake));
    }

    public function test_the_password_is_hashed_not_stored_as_typed(): void
    {
        $member = $this->member('Aaron', 'Kokodoko');

        $leader = MemberResource::promoteToLeader($member, [
            'username' => 'aaron.kokodoko',
            'password' => 'Flock2026!',
        ]);

        $this->assertNotSame('Flock2026!', $leader->password);
        $this->assertTrue(Hash::check('Flock2026!', $leader->password));
    }

    public function test_no_role_is_attached_when_none_was_chosen(): void
    {
        $member = $this->member('Aaron', 'Kokodoko');

        $leader = MemberResource::promoteToLeader($member, [
            'username' => 'aaron.kokodoko',
            'password' => 'x',
            'role_definition_id' => null,
        ]);

        $this->assertSame(0, LeaderRole::where('leader_id', $leader->id)->count());
        $this->assertTrue($leader->is_active, 'The login itself should still work.');
    }

    public function test_a_chosen_role_and_group_are_attached(): void
    {
        $type = GroupType::create([
            'name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 4,
            'tracks_attendance' => true, 'is_active' => true,
        ]);
        $bacenta = Group::create([
            'name' => 'Oerlikon', 'group_type_id' => $type->id, 'is_active' => true,
        ]);
        $role = RoleDefinition::create([
            'name' => 'Bacenta Leader', 'slug' => 'bacenta-leader',
            'permission_level' => 40, 'is_active' => true,
        ]);

        $member = $this->member('Aaron', 'Kokodoko');

        $leader = MemberResource::promoteToLeader($member, [
            'username' => 'aaron.kokodoko',
            'password' => 'x',
            'role_definition_id' => $role->id,
            'group_id' => $bacenta->id,
        ]);

        $attached = LeaderRole::where('leader_id', $leader->id)->firstOrFail();
        $this->assertSame($role->id, $attached->role_definition_id);
        $this->assertSame($bacenta->id, $attached->group_id);
        $this->assertTrue($attached->is_active);
    }

    public function test_the_member_and_leader_stay_linked(): void
    {
        $member = $this->member('Aaron', 'Kokodoko');

        MemberResource::promoteToLeader($member, ['username' => 'aaron.kokodoko', 'password' => 'x']);

        $this->assertNotNull($member->fresh()->leader, 'The action should be hidden for them afterwards.');
        $this->assertSame($member->id, Leader::firstOrFail()->member_id);
    }
}
