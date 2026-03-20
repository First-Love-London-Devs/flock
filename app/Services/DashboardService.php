<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Leader;
use App\Models\Member;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function getTotalMembers(?int $groupId = null): int
    {
        if (!$groupId) {
            return Member::active()->count();
        }

        $group = Group::find($groupId);
        if (!$group) {
            return 0;
        }

        $groupIds = collect([$groupId])->merge($group->descendants()->pluck('id'));

        return Member::active()
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $groupIds))
            ->count();
    }

    public function getAttendanceTrends(?int $groupId = null, int $weeks = 8): array
    {
        $startDate = Carbon::now()->subWeeks($weeks)->startOfWeek();
        $query = AttendanceSummary::where('date', '>=', $startDate)->orderBy('date');

        if ($groupId) {
            $group = Group::find($groupId);
            $groupIds = collect([$groupId]);
            if ($group) {
                $groupIds = $groupIds->merge($group->descendants()->pluck('id'));
            }
            $query->whereIn('group_id', $groupIds);
        }

        return $query->get()
            ->groupBy(fn ($s) => Carbon::parse($s->date)->startOfWeek()->format('Y-m-d'))
            ->map(fn ($week) => [
                'week' => Carbon::parse($week->first()->date)->startOfWeek()->format('Y-m-d'),
                'total_attendance' => $week->sum('total_attendance'),
                'visitor_count' => $week->sum('visitor_count'),
                'first_timer_count' => $week->sum('first_timer_count'),
                'submissions' => $week->count(),
            ])
            ->values()
            ->toArray();
    }

    public function getStats(?int $groupId = null): array
    {
        return [
            'total_members' => $this->getTotalMembers($groupId),
            'total_groups' => $groupId
                ? (Group::find($groupId)?->descendants()->count() ?? 0)
                : Group::active()->count(),
            'active_leaders' => Leader::where('is_active', true)->count(),
            'attendance_trends' => $this->getAttendanceTrends($groupId, 4),
        ];
    }
}
