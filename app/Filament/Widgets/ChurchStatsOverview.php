<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ChurchStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // These tables only exist in tenant databases, not the landlord
        if (!Schema::hasTable('members')) {
            return [
                Stat::make('Status', 'Central Admin Panel')
                    ->description('Use tenant domains to manage churches'),
            ];
        }

        $fourWeeksAgo = now()->subWeeks(4);

        /* A headline number leaks just as much as a list. Without this a
           Belgian admin's dashboard would read "Total Members 4,000" across
           all eight countries while every table under it showed Belgium,
           which is both a leak and simply wrong. */
        $ids = User::currentScopeIds();

        $recentSummaries = \App\Models\AttendanceSummary::where('date', '>=', $fourWeeksAgo)
            ->when($ids !== null, fn ($q) => $q->whereIn('group_id', $ids))
            ->get();
        $totalAttendance = $recentSummaries->sum('total_attendance');
        $weeksWithData = $recentSummaries->groupBy(fn ($s) => $s->date->startOfWeek()->toDateString())->count();
        $averageAttendance = $weeksWithData > 0 ? round($totalAttendance / $weeksWithData) : 0;

        return [
            Stat::make('Total Members', \App\Models\Member::active()
                ->when($ids !== null, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $ids)))
                ->count()),
            Stat::make('Total Groups', \App\Models\Group::active()
                ->when($ids !== null, fn ($q) => $q->whereIn('groups.id', $ids))
                ->count()),
            Stat::make('Active Leaders', \App\Models\Leader::where('is_active', true)
                ->when($ids !== null, fn ($q) => $q->whereHas('leaderRoles',
                    fn ($r) => $r->where('is_active', true)->whereIn('group_id', $ids)))
                ->count()),
            Stat::make('Avg Weekly Attendance (4wk)', $averageAttendance),
        ];
    }
}
