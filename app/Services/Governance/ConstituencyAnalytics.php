<?php

namespace App\Services\Governance;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Member;
use Carbon\Carbon;
use Carbon\CarbonInterval;
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

    protected function cellGroupIdsFor(Group $constituency): array
    {
        return Group::where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    protected function currentWeekBounds(): array
    {
        $start = Carbon::now()->startOfWeek();
        return [$start->toDateString(), $start->copy()->endOfWeek()->endOfDay()->toDateTimeString()];
    }
}
