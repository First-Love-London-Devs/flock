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
}
