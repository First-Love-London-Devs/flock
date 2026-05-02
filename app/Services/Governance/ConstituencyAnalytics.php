<?php

namespace App\Services\Governance;

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

    public function members(Group $constituency, int $perPage = 25): LengthAwarePaginator
    {
        $cellGroupIds = $this->cellGroupIdsFor($constituency);

        return Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function attendance(Group $constituency, CarbonPeriod $range): array
    {
        return $this->attendanceForCellGroups($this->cellGroupIdsFor($constituency), $range);
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
        $constituencyTypeId = \App\Models\GroupType::where('slug', 'constituency')->value('id');
        if (!$constituencyTypeId) return [];

        $constituencies = Group::where('group_type_id', $constituencyTypeId)
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
        $constituencyTypeId = \App\Models\GroupType::where('slug', 'constituency')->value('id');
        if (!$constituencyTypeId) return [];

        return Group::whereIn('parent_id', function ($q) use ($constituencyTypeId) {
            $q->select('id')->from('groups')->where('group_type_id', $constituencyTypeId);
        })
        ->where('is_active', true)
        ->pluck('id')
        ->all();
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
