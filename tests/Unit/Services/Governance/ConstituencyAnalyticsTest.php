<?php

namespace Tests\Unit\Services\Governance;

use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class ConstituencyAnalyticsTest extends TestCase
{
    use BuildsGovernanceFixtures;

    private ConstituencyAnalytics $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();
        $this->service = new ConstituencyAnalytics();
    }

    public function test_dashboard_aggregates_members_groups_leaders_and_attendance(): void
    {
        $constituency = $this->makeConstituency();
        $leader1 = \App\Models\Leader::factory()->create();
        $leader2 = \App\Models\Leader::factory()->create();

        $cellA = $this->makeCellGroup($constituency, $leader1);
        $cellB = $this->makeCellGroup($constituency, $leader2);

        for ($i = 0; $i < 5; $i++) $this->makeMember($cellA);
        for ($i = 0; $i < 7; $i++) $this->makeMember($cellB);

        $sunday = Carbon::now()->startOfWeek()->next('Sunday');
        $wednesday = Carbon::now()->startOfWeek()->next('Wednesday');

        $this->submitAttendance($cellA, $sunday, count: 4);
        $this->submitAttendance($cellB, $sunday, count: 6);
        $this->submitAttendance($cellA, $wednesday, count: 3);
        // cellB midweek: not submitted

        $result = $this->service->dashboard($constituency);

        $this->assertSame(12, $result['total_members']);
        $this->assertSame(2, $result['total_groups']);
        $this->assertSame(2, $result['total_leaders']);
        $this->assertSame(10, $result['sunday_attendance']);
        $this->assertSame(3, $result['midweek_attendance']);
        $this->assertSame(2, $result['groups_submitted_sunday']);
        $this->assertSame(1, $result['groups_submitted_midweek']);
    }

    public function test_groups_returns_each_cell_group_with_submission_flags_and_latest_attendance(): void
    {
        $constituency = $this->makeConstituency();
        $leader = \App\Models\Leader::factory()->create([
            'member_id' => \App\Models\Member::factory()->create(['first_name' => 'Kwame', 'last_name' => 'Asante'])->id,
        ]);

        $cell = $this->makeCellGroup($constituency, $leader, name: 'Grace Chapel');
        for ($i = 0; $i < 3; $i++) $this->makeMember($cell);

        $sunday = Carbon::now()->startOfWeek()->next('Sunday');
        $wednesday = Carbon::now()->startOfWeek()->next('Wednesday');
        $this->submitAttendance($cell, $sunday, count: 72);
        $this->submitAttendance($cell, $wednesday, count: 50);

        $groups = $this->service->groups($constituency);

        $this->assertCount(1, $groups);
        $this->assertSame('Grace Chapel', $groups[0]['name']);
        $this->assertSame(3, $groups[0]['members_count']);
        $this->assertSame('Kwame Asante', $groups[0]['leader_name']);
        $this->assertTrue($groups[0]['sunday_submitted']);
        $this->assertTrue($groups[0]['midweek_submitted']);
        $this->assertSame(72, $groups[0]['latest_sunday_attendance']);
        $this->assertSame(50, $groups[0]['latest_midweek_attendance']);
    }

    public function test_groups_returns_null_attendance_when_not_yet_submitted_this_week(): void
    {
        $constituency = $this->makeConstituency();
        $cell = $this->makeCellGroup($constituency);

        $groups = $this->service->groups($constituency);

        $this->assertCount(1, $groups);
        $this->assertFalse($groups[0]['sunday_submitted']);
        $this->assertFalse($groups[0]['midweek_submitted']);
        $this->assertNull($groups[0]['latest_sunday_attendance']);
        $this->assertNull($groups[0]['latest_midweek_attendance']);
        $this->assertNull($groups[0]['leader_name']);
    }

    public function test_group_detail_returns_group_fields_and_member_list(): void
    {
        $constituency = $this->makeConstituency();
        $cell = $this->makeCellGroup($constituency, name: 'Grace Chapel');

        $alice = $this->makeMember($cell);
        $alice->update(['first_name' => 'Alice', 'last_name' => 'Mensah', 'is_active' => true]);
        $bob = $this->makeMember($cell);
        $bob->update(['first_name' => 'Bob', 'last_name' => 'Tetteh', 'is_active' => false]);

        $detail = $this->service->groupDetail($constituency, $cell->id);

        $this->assertSame('Grace Chapel', $detail['name']);
        $this->assertCount(2, $detail['members']);

        $aliceRow = collect($detail['members'])->firstWhere('first_name', 'Alice');
        $this->assertSame('Mensah', $aliceRow['last_name']);
        $this->assertTrue($aliceRow['is_active']);

        $bobRow = collect($detail['members'])->firstWhere('first_name', 'Bob');
        $this->assertFalse($bobRow['is_active']);
    }

    public function test_group_detail_returns_null_when_group_not_in_constituency(): void
    {
        $constituencyA = $this->makeConstituency('A');
        $constituencyB = $this->makeConstituency('B');
        $cellInB = $this->makeCellGroup($constituencyB);

        $this->assertNull($this->service->groupDetail($constituencyA, $cellInB->id));
    }

    public function test_members_returns_paginated_unique_members_across_constituency(): void
    {
        $constituency = $this->makeConstituency();
        $cellA = $this->makeCellGroup($constituency);
        $cellB = $this->makeCellGroup($constituency);

        for ($i = 0; $i < 30; $i++) $this->makeMember($cellA);
        for ($i = 0; $i < 20; $i++) $this->makeMember($cellB);

        $page1 = $this->service->members($constituency, perPage: 25);

        $this->assertSame(50, $page1->total());
        $this->assertSame(25, $page1->perPage());
        $this->assertCount(25, $page1->items());
        $this->assertSame(1, $page1->currentPage());
    }

    public function test_members_excludes_members_in_groups_outside_constituency(): void
    {
        $constituencyA = $this->makeConstituency('A');
        $constituencyB = $this->makeConstituency('B');
        $cellA = $this->makeCellGroup($constituencyA);
        $cellB = $this->makeCellGroup($constituencyB);

        $this->makeMember($cellA);
        $this->makeMember($cellB);

        $page = $this->service->members($constituencyA);

        $this->assertSame(1, $page->total());
    }

    public function test_attendance_returns_series_summed_across_groups_split_by_day_of_week(): void
    {
        $constituency = $this->makeConstituency();
        $cellA = $this->makeCellGroup($constituency);
        $cellB = $this->makeCellGroup($constituency);

        $sunday1 = Carbon::create(2026, 4, 5);   // Sunday
        $wed1    = Carbon::create(2026, 4, 8);   // Wednesday
        $sunday2 = Carbon::create(2026, 4, 12);  // Sunday

        $this->submitAttendance($cellA, $sunday1, count: 50);
        $this->submitAttendance($cellB, $sunday1, count: 30);
        $this->submitAttendance($cellA, $wed1, count: 20);
        $this->submitAttendance($cellA, $sunday2, count: 60);

        $range = CarbonPeriod::create(Carbon::create(2026, 4, 1), Carbon::create(2026, 4, 30));

        $result = $this->service->attendance($constituency, $range);

        $this->assertSame(['sunday' => 140, 'midweek' => 20], $result['totals']);

        $byDate = collect($result['series'])->keyBy('date');
        $this->assertSame(80, $byDate['2026-04-05']['sunday']);
        $this->assertNull($byDate['2026-04-05']['midweek']);
        $this->assertNull($byDate['2026-04-08']['sunday']);
        $this->assertSame(20, $byDate['2026-04-08']['midweek']);
        $this->assertSame(60, $byDate['2026-04-12']['sunday']);
    }

    public function test_tenant_wide_attendance_aggregates_across_all_constituencies(): void
    {
        $constA = $this->makeConstituency('A');
        $constB = $this->makeConstituency('B');
        $cellA = $this->makeCellGroup($constA);
        $cellB = $this->makeCellGroup($constB);

        $sunday = Carbon::create(2026, 4, 5);
        $this->submitAttendance($cellA, $sunday, count: 30);
        $this->submitAttendance($cellB, $sunday, count: 40);

        $range = CarbonPeriod::create(Carbon::create(2026, 4, 1), Carbon::create(2026, 4, 30));

        $result = $this->service->tenantWideAttendance($range);

        $this->assertSame(70, $result['totals']['sunday']);
        $this->assertSame(0, $result['totals']['midweek']);
    }

    public function test_tenant_wide_members_paginates_across_all_constituencies(): void
    {
        $constA = $this->makeConstituency('A');
        $constB = $this->makeConstituency('B');
        $cellA = $this->makeCellGroup($constA);
        $cellB = $this->makeCellGroup($constB);

        for ($i = 0; $i < 10; $i++) $this->makeMember($cellA);
        for ($i = 0; $i < 10; $i++) $this->makeMember($cellB);

        $page = $this->service->tenantWideMembers(perPage: 25);

        $this->assertSame(20, $page->total());
    }

    public function test_tenant_wide_members_excludes_orphan_members(): void
    {
        $constA = $this->makeConstituency('A');
        $cellA = $this->makeCellGroup($constA);
        $this->makeMember($cellA);

        // Member in a group not parented to a Constituency
        $orphanCell = \App\Models\Group::factory()->create([
            'group_type_id' => $this->cellGroupType->id,
            'parent_id' => null,
        ]);
        $this->makeMember($orphanCell);

        $page = $this->service->tenantWideMembers();

        $this->assertSame(1, $page->total());
    }

    public function test_constituency_summaries_returns_one_row_per_constituency(): void
    {
        $constA = $this->makeConstituency('North');
        $constB = $this->makeConstituency('South');

        $cellA1 = $this->makeCellGroup($constA);
        $cellA2 = $this->makeCellGroup($constA);
        $cellB1 = $this->makeCellGroup($constB);

        for ($i = 0; $i < 5; $i++) $this->makeMember($cellA1);
        for ($i = 0; $i < 3; $i++) $this->makeMember($cellA2);
        for ($i = 0; $i < 7; $i++) $this->makeMember($cellB1);

        $governorA = $this->makeGovernor($constA);
        $governorA->member->update(['first_name' => 'Samuel', 'last_name' => 'Kofi']);
        // constB has no governor assigned

        $sunday = Carbon::now()->startOfWeek()->next('Sunday');
        $this->submitAttendance($cellA1, $sunday, count: 4);
        $this->submitAttendance($cellB1, $sunday, count: 6);

        $summaries = collect($this->service->constituencySummaries())->keyBy('constituency_name');

        $this->assertSame(8, $summaries['North']['total_members']);
        $this->assertSame(2, $summaries['North']['total_groups']);
        $this->assertSame(4, $summaries['North']['sunday_attendance']);
        $this->assertSame('Samuel', $summaries['North']['governor']['member']['first_name']);

        $this->assertSame(7, $summaries['South']['total_members']);
        $this->assertNull($summaries['South']['governor']);
    }
}
