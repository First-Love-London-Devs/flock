<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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

        $recentSummaries = \App\Models\AttendanceSummary::where('date', '>=', $fourWeeksAgo)->get();
        $totalAttendance = $recentSummaries->sum('total_attendance');
        $weeksWithData = $recentSummaries->groupBy(fn ($s) => $s->date->startOfWeek()->toDateString())->count();
        $averageAttendance = $weeksWithData > 0 ? round($totalAttendance / $weeksWithData) : 0;

        return [
            Stat::make('Total Members', \App\Models\Member::active()->count()),
            Stat::make('Total Groups', \App\Models\Group::active()->count()),
            Stat::make('Active Leaders', \App\Models\Leader::where('is_active', true)->count()),
            Stat::make('Avg Weekly Attendance (4wk)', $averageAttendance),
        ];
    }
}
