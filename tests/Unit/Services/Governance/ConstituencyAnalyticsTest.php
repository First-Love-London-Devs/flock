<?php

namespace Tests\Unit\Services\Governance;

use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
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
}
