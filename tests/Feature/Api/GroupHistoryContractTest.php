<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

/**
 * The contract the app's group-detail screen stands on.
 *
 * FlockApp's AttendanceRow read total_present and total_absent — fields that
 * have never existed — and rendered a blank count and 0% on every row. The
 * fix (FlockApp master, held from OTA until this proved it) reads
 * total_attendance and derives turnout from the attendances rows. This test
 * pins that contract server-side: those fields ARE on the payload, the rows
 * ride along, and the phantom fields are still absent so nobody quietly
 * reintroduces the app's old assumption.
 */
class GroupHistoryContractTest extends TestCase
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
        \App\Services\DomainScope::forget();
    }

    public function test_group_history_carries_what_the_app_reads_and_not_what_it_used_to(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);

        $present = $this->makeMember($cell);
        $absent = $this->makeMember($cell);

        $summary = AttendanceSummary::create([
            'group_id' => $cell->id,
            'date' => now()->toDateString(),
            'total_attendance' => 1,
            'visitor_count' => 0,
            'first_timer_count' => 0,
            'submitted_by_leader_id' => $governor->id,
        ]);
        Attendance::create(['attendance_summary_id' => $summary->id, 'member_id' => $present->id, 'attended' => true]);
        Attendance::create(['attendance_summary_id' => $summary->id, 'member_id' => $absent->id, 'attended' => false]);

        $row = $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/attendance/group/{$cell->id}")
            ->assertOk()
            ->json('data.data.0');

        // What the fixed app reads.
        $this->assertSame(1, $row['total_attendance']);
        $this->assertCount(2, $row['attendances'], 'The roll rides along — one row per member.');
        $this->assertSame(
            [true, false],
            array_column($row['attendances'], 'attended'),
            'attended flags are what turnout is derived from.'
        );

        // What the broken app read, pinned absent.
        $this->assertArrayNotHasKey('total_present', $row);
        $this->assertArrayNotHasKey('total_absent', $row);
    }
}
