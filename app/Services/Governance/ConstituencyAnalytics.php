<?php

namespace App\Services\Governance;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Member;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;

class ConstituencyAnalytics
{
    public function dashboard(Group $constituency): array
    {
        $cellGroupIds = $this->cellGroupIdsFor($constituency);

        $totalMembers = Member::whereHas('groups', fn ($q) =>
            $q->whereIn('groups.id', $cellGroupIds)
        )->count();

        // total_leaders = distinct leader_id assignments across the constituency's cell groups
        $totalLeaders = Group::whereIn('id', $cellGroupIds)
            ->whereNotNull('leader_id')
            ->distinct('leader_id')
            ->count('leader_id');

        [$weekStart, $weekEnd] = $this->currentWeekBounds();
        $thisWeekSummaries = AttendanceSummary::whereIn('group_id', $cellGroupIds)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        $sundayRows = $thisWeekSummaries->filter(fn ($s) => Carbon::parse($s->date)->isSunday());
        $midweekRows = $thisWeekSummaries->reject(fn ($s) => Carbon::parse($s->date)->isSunday());

        return [
            'total_members' => $totalMembers,
            'total_groups' => count($cellGroupIds),
            'total_leaders' => $totalLeaders,
            'sunday_attendance' => (int) $sundayRows->sum('total_attendance'),
            'midweek_attendance' => (int) $midweekRows->sum('total_attendance'),
            'groups_submitted_sunday' => $sundayRows->pluck('group_id')->unique()->count(),
            'groups_submitted_midweek' => $midweekRows->pluck('group_id')->unique()->count(),
        ];
    }

    public function groups(Group $constituency): array
    {
        [$weekStart, $weekEnd] = $this->currentWeekBounds();

        $cellGroups = Group::where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->with(['leader.member'])
            ->withCount('members')
            ->get();

        $cellGroupIds = $cellGroups->pluck('id')->all();
        $thisWeek = AttendanceSummary::whereIn('group_id', $cellGroupIds)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get()
            ->groupBy('group_id');

        return $cellGroups->map(function ($g) use ($thisWeek) {
            $rows = $thisWeek->get($g->id, collect());
            $sundayRow = $rows->first(fn ($r) => Carbon::parse($r->date)->isSunday());
            $midweekRow = $rows->first(fn ($r) => !Carbon::parse($r->date)->isSunday());

            $leaderMember = $g->leader?->member;

            return [
                'id' => $g->id,
                'name' => $g->name,
                'members_count' => $g->members_count,
                'leader_name' => $leaderMember ? trim($leaderMember->first_name . ' ' . $leaderMember->last_name) : null,
                'sunday_submitted' => (bool) $sundayRow,
                'midweek_submitted' => (bool) $midweekRow,
                'latest_sunday_attendance' => $sundayRow?->total_attendance,
                'latest_midweek_attendance' => $midweekRow?->total_attendance,
            ];
        })->all();
    }

    public function groupDetail(Group $constituency, int $groupId): ?array
    {
        $group = Group::where('id', $groupId)
            ->where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->with(['leader.member', 'members'])
            ->withCount('members')
            ->first();

        if (!$group) {
            return null;
        }

        [$weekStart, $weekEnd] = $this->currentWeekBounds();
        $rows = AttendanceSummary::where('group_id', $group->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        $sundayRow = $rows->first(fn ($r) => Carbon::parse($r->date)->isSunday());
        $midweekRow = $rows->first(fn ($r) => !Carbon::parse($r->date)->isSunday());
        $leaderMember = $group->leader?->member;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'members_count' => $group->members_count,
            'leader_name' => $leaderMember ? trim($leaderMember->first_name . ' ' . $leaderMember->last_name) : null,
            'sunday_submitted' => (bool) $sundayRow,
            'midweek_submitted' => (bool) $midweekRow,
            'latest_sunday_attendance' => $sundayRow?->total_attendance,
            'latest_midweek_attendance' => $midweekRow?->total_attendance,
            'members' => $group->members->map(fn ($m) => [
                'id' => $m->id,
                'first_name' => $m->first_name,
                'last_name' => $m->last_name,
                'is_active' => (bool) $m->is_active,
            ])->values()->all(),
        ];
    }

    public function members(Group $constituency, int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $cellGroupIds = $this->cellGroupIdsFor($constituency);

        $query = Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds));

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('phone_number', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function attendance(Group $constituency, string $serviceType, ?Carbon $date = null): array
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();
        $cellGroups = Group::where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = AttendanceSummary::whereIn('group_id', $cellGroups->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('group_id');

        $rowMatchesService = function (AttendanceSummary $r) use ($serviceType): bool {
            $isSunday = Carbon::parse($r->date)->isSunday();
            return $serviceType === 'sunday' ? $isSunday : !$isSunday;
        };

        $matched = $rows->filter($rowMatchesService);

        // Per-summary ministered/rehearsed tallies (ministry groups only set
        // these; non-ministry groups contribute 0). Single grouped query.
        $ministryTallies = Attendance::whereIn('attendance_summary_id', $matched->pluck('id'))
            ->selectRaw('attendance_summary_id, SUM(ministered) as ministered, SUM(rehearsed) as rehearsed')
            ->groupBy('attendance_summary_id')
            ->get()
            ->keyBy('attendance_summary_id');

        $byGroup = $cellGroups->map(function (Group $g) use ($rows, $rowMatchesService, $ministryTallies) {
            $row = $rows->get($g->id);
            $submitted = $row && $rowMatchesService($row);
            $tally = $submitted ? $ministryTallies->get($row->id) : null;

            return [
                'group_id' => $g->id,
                'group_name' => $g->name,
                'attendance' => $submitted ? (int) $row->total_attendance : null,
                'ministered' => $tally ? (int) $tally->ministered : ($submitted ? 0 : null),
                'rehearsed' => $tally ? (int) $tally->rehearsed : ($submitted ? 0 : null),
                'submitted' => $submitted,
                'attendance_summary_id' => $submitted ? (int) $row->id : null,
            ];
        });

        $totalAttendance = (int) $matched->sum('total_attendance');
        $visitorCount = (int) $matched->sum('visitor_count');
        $totalMinistered = (int) $ministryTallies->sum('ministered');
        $totalRehearsed = (int) $ministryTallies->sum('rehearsed');

        return [
            'date' => $date->toDateString(),
            'service_type' => $serviceType,
            'total_attendance' => $totalAttendance,
            'member_count' => max(0, $totalAttendance - $visitorCount),
            'visitor_count' => $visitorCount,
            'total_ministered' => $totalMinistered,
            'total_rehearsed' => $totalRehearsed,
            'groups_submitted' => $matched->count(),
            'total_groups' => $cellGroups->count(),
            'by_group' => $byGroup->values()->all(),
        ];
    }

    public function tenantWideAttendance(CarbonPeriod $range): array
    {
        $cellGroupIds = $this->allConstituencyCellGroupIds();
        return $this->attendanceForCellGroups($cellGroupIds, $range);
    }

    public function tenantWideMembers(int $perPage = 25): LengthAwarePaginator
    {
        $cellGroupIds = $this->allConstituencyCellGroupIds();

        return Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($perPage);
    }

    public function constituencySummaries(): array
    {
        [$weekStart, $weekEnd] = $this->currentWeekBounds();
        $constituencyTypeIds = $this->constituencyGroupTypeIds();
        if ($constituencyTypeIds->isEmpty()) return [];

        $constituencies = Group::whereIn('group_type_id', $constituencyTypeIds)
            ->where('is_active', true)
            ->get();

        return $constituencies->map(function (Group $c) use ($weekStart, $weekEnd) {
            $cellGroupIds = $this->cellGroupIdsFor($c);

            $totalMembers = Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))->count();

            $rows = AttendanceSummary::whereIn('group_id', $cellGroupIds)
                ->whereBetween('date', [$weekStart, $weekEnd])
                ->get();

            $sunday = (int) $rows->filter(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');
            $midweek = (int) $rows->reject(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');

            $governorRole = \App\Models\LeaderRole::where('group_id', $c->id)
                ->where('is_active', true)
                ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
                ->with('leader.member')
                ->first();

            $governor = null;
            if ($governorRole && $governorRole->leader) {
                $governor = [
                    'id' => $governorRole->leader->id,
                    'member' => [
                        'id' => $governorRole->leader->member->id,
                        'first_name' => $governorRole->leader->member->first_name,
                        'last_name' => $governorRole->leader->member->last_name,
                    ],
                ];
            }

            return [
                'id' => $c->id,
                'constituency_name' => $c->name,
                'total_members' => $totalMembers,
                'total_groups' => count($cellGroupIds),
                'sunday_attendance' => $sunday,
                'midweek_attendance' => $midweek,
                'governor' => $governor,
            ];
        })->all();
    }

    protected function cellGroupIdsFor(Group $constituency): array
    {
        return Group::where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    protected function currentWeekBounds(): array
    {
        // Upper bound is end-of-day datetime so SQLite (which stores Eloquent date casts
        // with a 00:00:00 time) still includes rows on the final day in whereBetween.
        $start = Carbon::now()->startOfWeek();
        return [$start->toDateString(), $start->copy()->endOfWeek()->endOfDay()->toDateTimeString()];
    }

    protected function allConstituencyCellGroupIds(): array
    {
        $constituencyTypeIds = $this->constituencyGroupTypeIds();
        if ($constituencyTypeIds->isEmpty()) return [];

        return Group::whereIn('parent_id', function ($q) use ($constituencyTypeIds) {
            $q->select('id')->from('groups')->whereIn('group_type_id', $constituencyTypeIds);
        })
        ->where('is_active', true)
        ->pluck('id')
        ->all();
    }

    protected function constituencyGroupTypeIds(): \Illuminate\Support\Collection
    {
        return \App\Models\GroupType::whereIn('slug', ['constituency', 'governor'])->pluck('id');
    }

    protected function attendanceForCellGroups(array $cellGroupIds, CarbonPeriod $range): array
    {
        // End-of-day on the upper bound so SQLite (date casts stored with 00:00:00 time)
        // includes rows on the final day in whereBetween.
        $start = Carbon::parse($range->getStartDate())->toDateString();
        $end = Carbon::parse($range->getEndDate())->endOfDay()->toDateTimeString();

        $rows = AttendanceSummary::whereIn('group_id', $cellGroupIds)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        $byDate = $rows->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $series = $byDate->map(function ($dayRows, $date) {
            $isSunday = Carbon::parse($date)->isSunday();
            $sum = (int) $dayRows->sum('total_attendance');
            return ['date' => $date, 'sunday' => $isSunday ? $sum : null, 'midweek' => $isSunday ? null : $sum];
        })->values()->all();

        $totalSunday = (int) $rows->filter(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');
        $totalMidweek = (int) $rows->reject(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');

        return ['series' => $series, 'totals' => ['sunday' => $totalSunday, 'midweek' => $totalMidweek]];
    }
}
