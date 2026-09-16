<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use Carbon\Carbon;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

/**
 * The admin's power to appoint on-the-ground leaders and to see which of their
 * Bacentas have submitted this week. Appointing overseers is out of reach on
 * purpose — an admin makes cell and ministry leaders and nothing higher.
 */
class AdminLeadershipTest extends TestCase
{
    use BuildsGovernanceFixtures;

    private RoleDefinition $adminRole;
    private RoleDefinition $cellLeaderRole;
    private RoleDefinition $ministryLeaderRole;
    private Group $constituency;
    private Group $bacenta;
    private Leader $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
        $this->seedGovernanceTypes();

        $this->adminRole = RoleDefinition::factory()->create([
            'name' => 'Admin', 'slug' => 'admin', 'permission_level' => 50, 'applies_to_group_type_id' => null,
        ]);
        $this->cellLeaderRole = RoleDefinition::factory()->create([
            'name' => 'Cell Leader', 'slug' => 'cell-leader', 'permission_level' => 40,
            'applies_to_group_type_id' => $this->cellGroupType->id,
        ]);
        $this->ministryLeaderRole = RoleDefinition::factory()->create([
            'name' => 'Ministry Leader', 'slug' => 'ministry-leader', 'permission_level' => 40,
            'applies_to_group_type_id' => null,
        ]);

        $this->constituency = $this->makeConstituency('Test Constituency');
        $this->bacenta = $this->makeCellGroup($this->constituency, null, 'Test Bacenta');
        $this->admin = $this->makeAdmin($this->constituency);
    }

    private function makeAdmin(Group $group): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->adminRole->id,
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        return $leader;
    }

    // ─── Assigning ────────────────────────────────────────────────────────────

    public function test_assigning_a_role_to_a_plain_member_creates_a_login_and_returns_credentials_once(): void
    {
        $member = $this->makeMember($this->bacenta);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'cell-leader',
                'group_id' => $this->bacenta->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.role.role_slug', 'cell-leader')
            ->assertJsonPath('data.credentials.username', fn ($u) => is_string($u) && $u !== '');

        $password = $res->json('data.credentials.password');
        $this->assertNotEmpty($password);

        $leader = Leader::where('member_id', $member->id)->first();
        $this->assertNotNull($leader);
        $this->assertDatabaseHas('leader_roles', [
            'leader_id' => $leader->id,
            'role_definition_id' => $this->cellLeaderRole->id,
            'group_id' => $this->bacenta->id,
            'is_active' => true,
        ]);
    }

    public function test_a_second_role_on_an_existing_leader_returns_no_new_credentials(): void
    {
        $member = $this->makeMember($this->bacenta);
        $sonta = Group::factory()->create([
            'name' => 'Choir', 'group_type_id' => $this->constituencyType->id, 'parent_id' => $this->constituency->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'cell-leader', 'group_id' => $this->bacenta->id,
            ])->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'ministry-leader', 'group_id' => $sonta->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.credentials', null);

        $this->assertEquals(1, Leader::where('member_id', $member->id)->count());
    }

    public function test_assigning_the_same_role_twice_does_not_stack(): void
    {
        $member = $this->makeMember($this->bacenta);

        foreach (range(1, 2) as $_) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                    'role_slug' => 'cell-leader', 'group_id' => $this->bacenta->id,
                ])->assertOk();
        }

        $leader = Leader::where('member_id', $member->id)->first();
        $this->assertEquals(1, LeaderRole::where('leader_id', $leader->id)
            ->where('role_definition_id', $this->cellLeaderRole->id)
            ->where('group_id', $this->bacenta->id)
            ->count());
    }

    public function test_cannot_assign_a_role_outside_the_grantable_set(): void
    {
        $member = $this->makeMember($this->bacenta);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'governor', 'group_id' => $this->bacenta->id,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_assign_a_role_on_a_group_outside_scope(): void
    {
        $member = $this->makeMember($this->bacenta);
        $otherConstituency = $this->makeConstituency('Elsewhere');
        $otherBacenta = $this->makeCellGroup($otherConstituency, null, 'Far Bacenta');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'cell-leader', 'group_id' => $otherBacenta->id,
            ])
            ->assertStatus(403);
    }

    // ─── Reading ──────────────────────────────────────────────────────────────

    public function test_member_leadership_lists_roles_and_the_grantable_set(): void
    {
        $member = $this->makeMember($this->bacenta);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'cell-leader', 'group_id' => $this->bacenta->id,
            ])->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/members/{$member->id}/leadership")
            ->assertOk()
            ->assertJsonPath('data.has_login', true)
            ->assertJsonPath('data.roles.0.role_slug', 'cell-leader')
            ->assertJsonPath('data.roles.0.removable', true)
            ->assertJsonCount(2, 'data.grantable_roles');
    }

    // ─── Removing ─────────────────────────────────────────────────────────────

    public function test_removing_a_granted_role(): void
    {
        $member = $this->makeMember($this->bacenta);
        $assign = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/leadership", [
                'role_slug' => 'cell-leader', 'group_id' => $this->bacenta->id,
            ])->assertOk();
        $roleId = $assign->json('data.role.id');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/members/{$member->id}/leadership/{$roleId}")
            ->assertOk();

        $this->assertDatabaseMissing('leader_roles', ['id' => $roleId]);
    }

    public function test_cannot_remove_a_role_above_the_grantable_set(): void
    {
        // A member who is also a governor: the admin may not strip that.
        $member = $this->makeMember($this->bacenta);
        $leader = Leader::factory()->create(['member_id' => $member->id]);
        $governorRole = LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->governorRole->id,
            'group_id' => $this->constituency->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/members/{$member->id}/leadership/{$governorRole->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('leader_roles', ['id' => $governorRole->id]);
    }

    // ─── Submissions ──────────────────────────────────────────────────────────

    public function test_submissions_reports_sunday_and_midweek_for_this_weeks_bacentas(): void
    {
        $submitted = $this->makeCellGroup($this->constituency, null, 'Submitted Bacenta');
        // $this->bacenta stays empty (never submitted).

        $sunday = Carbon::now()->startOfWeek()->addDays(6);   // this week's Sunday
        $wednesday = Carbon::now()->startOfWeek()->addDays(2); // this week's midweek
        $this->submitAttendance($submitted, $sunday, 40);
        $this->submitAttendance($submitted, $wednesday, 25);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/submissions')
            ->assertOk()
            ->assertJsonPath('data.sunday_submitted', 1)
            ->assertJsonPath('data.midweek_submitted', 1);

        $rows = collect($res->json('data.bacentas'));
        $this->assertTrue((bool) $rows->firstWhere('id', $submitted->id)['sunday_submitted']);
        $this->assertFalse((bool) $rows->firstWhere('id', $this->bacenta->id)['sunday_submitted']);
    }
}
