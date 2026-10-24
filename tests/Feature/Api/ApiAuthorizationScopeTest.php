<?php

namespace Tests\Feature\Api;

use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

/**
 * The tenant API's flat `auth:sanctum` block used to gate a block of routes on
 * "is this a logged-in leader" alone, with no role check and no per-record
 * scope. That let any leader with an app login (every cell leader has one):
 *   - read / edit / delete members and groups in other churches (IDOR), and
 *   - create leaders, reset passwords, and grant themselves tenant-wide
 *     super-admin via the leader/role/settings/notification endpoints.
 *
 * This locks both down: per-record endpoints are confined to the caller's
 * scope, and the admin-only mutations are gated behind the admin role. The
 * endpoints the mobile app actually uses as an ordinary leader (scoped reads
 * and in-scope member edits) must keep working.
 */
class ApiAuthorizationScopeTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
        $this->seedGovernanceTypes();
    }

    /** A leader governing only the North constituency. */
    private function northGovernor(): array
    {
        $north = $this->makeConstituency('North');
        $governor = $this->makeGovernor($north);
        $cell = $this->makeCellGroup($north);
        $member = $this->makeMember($cell);

        return [$governor, $north, $cell, $member];
    }

    private function makeAdmin(): Leader
    {
        $adminRole = RoleDefinition::factory()->create([
            'name' => 'Admin', 'slug' => 'admin', 'permission_level' => 100,
        ]);
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $adminRole->id,
            'group_id' => null,
            'is_active' => true,
        ]);

        return $leader;
    }

    // ── IDOR: per-record access is confined to the caller's scope ──────────

    public function test_leader_cannot_read_a_member_in_another_scope(): void
    {
        [$governor] = $this->northGovernor();
        $southMember = $this->makeMember($this->makeCellGroup($this->makeConstituency('South')));

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/members/{$southMember->id}")
            ->assertStatus(403);
    }

    public function test_leader_cannot_update_or_delete_a_member_in_another_scope(): void
    {
        [$governor] = $this->northGovernor();
        $southMember = $this->makeMember($this->makeCellGroup($this->makeConstituency('South')));

        $this->actingAs($governor, 'sanctum')
            ->putJson("/api/v1/members/{$southMember->id}", ['first_name' => 'Hacked'])
            ->assertStatus(403);

        $this->actingAs($governor, 'sanctum')
            ->deleteJson("/api/v1/members/{$southMember->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('members', ['id' => $southMember->id, 'first_name' => $southMember->first_name]);
    }

    public function test_leader_cannot_read_a_group_in_another_scope(): void
    {
        [$governor] = $this->northGovernor();
        $south = $this->makeConstituency('South');

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/groups/{$south->id}")
            ->assertStatus(403);
    }

    public function test_leader_can_still_read_and_edit_in_their_own_scope(): void
    {
        [$governor, $north, , $member] = $this->northGovernor();

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/groups/{$north->id}")
            ->assertOk();

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        // The flow the mobile app relies on: an in-scope member edit succeeds.
        $this->actingAs($governor, 'sanctum')
            ->putJson("/api/v1/members/{$member->id}", ['first_name' => 'Edited'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Edited');
    }

    public function test_a_missing_member_is_404_not_403(): void
    {
        [$governor] = $this->northGovernor();

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/members/99999')
            ->assertStatus(404);
    }

    // ── Privilege escalation: admin-only mutations reject ordinary leaders ──

    public function test_ordinary_leader_cannot_grant_roles_or_touch_leaders(): void
    {
        [$governor] = $this->northGovernor();
        $target = Leader::factory()->create();

        $this->actingAs($governor, 'sanctum')
            ->postJson("/api/v1/leaders/{$target->id}/assign-role", [
                'role_definition_id' => $this->governorRole->id,
                'group_id' => null,
            ])->assertStatus(403);

        $this->actingAs($governor, 'sanctum')
            ->putJson("/api/v1/leaders/{$target->id}", ['password' => 'takenover123'])
            ->assertStatus(403);

        $newMember = Member::factory()->create();
        $this->actingAs($governor, 'sanctum')
            ->postJson('/api/v1/leaders', [
                'member_id' => $newMember->id, 'username' => 'sneaky', 'password' => 'password123',
            ])->assertStatus(403);

        $this->assertDatabaseMissing('leaders', ['username' => 'sneaky']);
    }

    public function test_ordinary_leader_cannot_write_settings_or_broadcast(): void
    {
        [$governor] = $this->northGovernor();

        $this->actingAs($governor, 'sanctum')
            ->putJson('/api/v1/settings/church_name', ['value' => 'Pwned', 'type' => 'string'])
            ->assertStatus(403);

        $this->actingAs($governor, 'sanctum')
            ->postJson('/api/v1/notifications/send', [
                'title' => 'x', 'body' => 'y', 'role_slug' => 'governor',
            ])->assertStatus(403);
    }

    public function test_admin_passes_the_role_gate(): void
    {
        $admin = $this->makeAdmin();
        $target = Leader::factory()->create();

        // Proof the gate discriminates by role, not that it blocks everyone:
        // the same assign-role call an ordinary leader is refused succeeds here.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leaders/{$target->id}/assign-role", [
                'role_definition_id' => $this->governorRole->id,
                'group_id' => null,
            ])->assertStatus(201);
    }
}
