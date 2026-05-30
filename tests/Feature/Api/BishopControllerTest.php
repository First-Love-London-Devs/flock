<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class BishopControllerTest extends TestCase
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

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/bishop/governors')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertStatus(403);
    }

    public function test_governors_returns_summary_per_constituency(): void
    {
        $bishop = $this->makeBishop();
        $constA = $this->makeConstituency('North');
        $constB = $this->makeConstituency('South');
        $this->makeGovernor($constA);

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertOk();

        $this->assertSame(true, $r->json('success'));
        $this->assertCount(2, $r->json('data'));
    }

    public function test_governors_returns_empty_array_for_empty_tenant(): void
    {
        $bishop = $this->makeBishop();

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => []]);
    }

    public function test_tenant_wide_attendance_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        $this->submitAttendance($cell, Carbon::create(2026, 4, 5), 50);

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/attendance?from=2026-04-01&to=2026-04-30')
            ->assertOk();

        $this->assertSame(50, $r->json('data.totals.sunday'));
    }

    public function test_tenant_wide_summary_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        $this->submitAttendance($cell, Carbon::create(2026, 4, 5), 50);

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/summary?service_type=sunday&date=2026-04-05')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'date', 'service_type',
                'total_attendance', 'member_count', 'visitor_count', 'total_members',
                'first_timer_count', 'new_convert_count',
                'groups_submitted', 'total_groups',
            ]]);

        $this->assertSame('sunday', $r->json('data.service_type'));
        $this->assertSame(50, $r->json('data.total_attendance'));
        $this->assertSame(1, $r->json('data.groups_submitted'));
    }

    public function test_summary_without_date_falls_back_to_most_recent_service_day(): void
    {
        // The mobile app calls /bishop/summary with no date, so the most-recent-
        // matching-service fallback path must work and not error.
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        $this->submitAttendance($cell, Carbon::create(2026, 4, 5), 42); // a Sunday

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/summary?service_type=sunday')
            ->assertOk();

        $this->assertSame('2026-04-05', $r->json('data.date'));
        $this->assertSame(42, $r->json('data.total_attendance'));
    }

    public function test_summary_empty_tenant_does_not_error(): void
    {
        $bishop = $this->makeBishop();

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/summary?service_type=sunday')
            ->assertOk()
            ->assertJsonPath('data.total_attendance', 0)
            ->assertJsonPath('data.total_groups', 0);
    }

    public function test_tenant_wide_members_paginates(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        for ($i = 0; $i < 5; $i++) $this->makeMember($cell);

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/members?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.per_page', 2);
    }

    public function test_governor_dashboard_drilldown(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $this->makeGovernor($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$const->id}/dashboard")
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_members', 'total_groups']]);
    }

    public function test_governor_drilldown_404s_for_unknown_constituency_id(): void
    {
        $bishop = $this->makeBishop();

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/governors/999999/dashboard')
            ->assertStatus(404);
    }

    public function test_governor_drilldown_groups_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $this->makeGovernor($const);
        $this->makeCellGroup($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$const->id}/groups")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_governor_drilldown_attendance_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $this->makeGovernor($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$const->id}/attendance?service_type=sunday&date=2026-04-05")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'date', 'service_type',
                'total_attendance', 'member_count', 'visitor_count',
                'groups_submitted', 'total_groups',
                'by_group',
            ]]);
    }

    public function test_group_detail_drilldown(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $this->makeGovernor($const);
        $cell = $this->makeCellGroup($const);
        $this->makeMember($cell);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$const->id}/groups/{$cell->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'members']]);
    }

    public function test_group_detail_drilldown_404s_for_group_outside_constituency(): void
    {
        $bishop = $this->makeBishop();
        $constA = $this->makeConstituency('A');
        $constB = $this->makeConstituency('B');
        $cellInB = $this->makeCellGroup($constB);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$constA->id}/groups/{$cellInB->id}")
            ->assertStatus(404);
    }
}
