<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use BuildsGovernanceFixtures;

    private RoleDefinition $adminRole;
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
            'name' => 'Admin',
            'slug' => 'admin',
            'permission_level' => 50,
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
            'leader_id'          => $leader->id,
            'role_definition_id' => $this->adminRole->id,
            'group_id'           => $group->id,
            'is_active'          => true,
        ]);
        return $leader;
    }

    // ─── Auth guards ────────────────────────────────────────────────────────

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/members')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected(): void
    {
        $bishop = $this->makeBishop();
        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/admin/members')
            ->assertStatus(403);
    }

    // ─── Members ────────────────────────────────────────────────────────────

    public function test_list_members_returns_only_scoped_members(): void
    {
        $inScope = $this->makeMember($this->bacenta);

        $otherConstituency = $this->makeConstituency('Other');
        $otherBacenta = $this->makeCellGroup($otherConstituency);
        $outOfScope = $this->makeMember($otherBacenta);

        $data = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/members')
            ->assertOk()
            ->json('data.data');

        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($inScope->id));
        $this->assertFalse($ids->contains($outOfScope->id));
    }

    public function test_create_member_without_bacenta(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/members', [
                'first_name' => 'Jane',
                'last_name'  => 'Doe',
            ])
            ->assertOk();

        $this->assertEquals('Jane', $r->json('data.first_name'));
        $this->assertTrue($r->json('data.is_active'));
    }

    public function test_create_member_assigns_to_bacenta(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/members', [
                'first_name' => 'John',
                'last_name'  => 'Smith',
                'bacenta_id' => $this->bacenta->id,
            ])
            ->assertOk();

        $memberId = $r->json('data.id');
        $this->assertDatabaseHas('group_member', [
            'member_id' => $memberId,
            'group_id'  => $this->bacenta->id,
        ]);
    }

    public function test_create_member_rejects_out_of_scope_bacenta(): void
    {
        $otherBacenta = $this->makeCellGroup($this->makeConstituency('Other'));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/members', [
                'first_name' => 'Test',
                'last_name'  => 'Person',
                'bacenta_id' => $otherBacenta->id,
            ])
            ->assertStatus(403);
    }

    public function test_update_member(): void
    {
        $member = $this->makeMember($this->bacenta);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/members/{$member->id}", ['first_name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Updated');
    }

    public function test_deactivate_member(): void
    {
        $member = $this->makeMember($this->bacenta);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/members/{$member->id}")
            ->assertOk();

        $this->assertFalse($member->fresh()->is_active);
    }

    public function test_cannot_access_out_of_scope_member(): void
    {
        $other = $this->makeMember($this->makeCellGroup($this->makeConstituency('X')));

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/members/{$other->id}")
            ->assertStatus(403);
    }

    // ─── Bacentas ───────────────────────────────────────────────────────────

    public function test_list_bacentas_returns_only_scoped_bacentas(): void
    {
        $other = $this->makeCellGroup($this->makeConstituency('Other'));

        $data = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/bacentas')
            ->assertOk()
            ->json('data');

        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($this->bacenta->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_create_bacenta(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bacentas', ['name' => 'New Bacenta'])
            ->assertOk();

        $this->assertEquals('New Bacenta', $r->json('data.name'));
        $this->assertEquals($this->constituency->id, Group::find($r->json('data.id'))->parent_id);
    }

    public function test_update_bacenta(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/bacentas/{$this->bacenta->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_deactivate_bacenta(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/bacentas/{$this->bacenta->id}")
            ->assertOk();

        $this->assertFalse($this->bacenta->fresh()->is_active);
    }

    public function test_cannot_access_out_of_scope_bacenta(): void
    {
        $other = $this->makeCellGroup($this->makeConstituency('X'));

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/bacentas/{$other->id}")
            ->assertStatus(403);
    }
}
