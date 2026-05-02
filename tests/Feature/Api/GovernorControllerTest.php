<?php

namespace Tests\Feature\Api;

use App\Models\Member;
use Carbon\Carbon;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class GovernorControllerTest extends TestCase
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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/governor/dashboard')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected(): void
    {
        $bishop = $this->makeBishop();
        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertStatus(403);
    }

    public function test_dashboard_returns_documented_shape(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        $this->makeMember($cell);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => [
                'total_members', 'total_groups', 'total_leaders',
                'sunday_attendance', 'midweek_attendance',
                'groups_submitted_sunday', 'groups_submitted_midweek',
            ]]);
    }

    public function test_groups_returns_array(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $this->makeCellGroup($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/groups')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_group_detail_404s_for_group_outside_constituency(): void
    {
        $myConstituency = $this->makeConstituency('Mine');
        $otherConstituency = $this->makeConstituency('Other');
        $governor = $this->makeGovernor($myConstituency);
        $foreignCell = $this->makeCellGroup($otherConstituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/governor/groups/{$foreignCell->id}")
            ->assertStatus(404);
    }

    public function test_group_detail_returns_members(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        $this->makeMember($cell);

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/governor/groups/{$cell->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'members' => [['id', 'first_name', 'last_name', 'is_active']]]]);
    }

    public function test_members_paginates(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        for ($i = 0; $i < 30; $i++) $this->makeMember($cell);

        $r = $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/members?per_page=10')
            ->assertOk();

        $this->assertSame(10, count($r->json('data.data')));
        $this->assertSame(30, $r->json('data.total'));
    }

    public function test_attendance_returns_series_and_totals(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);

        $sunday = Carbon::create(2026, 4, 5);
        $this->submitAttendance($cell, $sunday, 50);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/attendance?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonStructure(['data' => ['series', 'totals' => ['sunday', 'midweek']]]);
    }

    public function test_misconfigured_governor_with_null_group_id_returns_403(): void
    {
        $leader = \App\Models\Leader::factory()->create();
        \App\Models\LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->governorRole->id,
            'group_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($leader, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
