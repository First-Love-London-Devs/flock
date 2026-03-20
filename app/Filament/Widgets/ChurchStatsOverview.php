<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Leader;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChurchStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $fourWeeksAgo = Carbon::now()->subWeeks(4);

        $recentSummaries = AttendanceSummary::where('date', '>=', $fourWeeksAgo)->get();
        $totalAttendance = $recentSummaries->sum('total_attendance');
        $weeksWithData = $recentSummaries->groupBy(fn ($s) => Carbon::parse($s->date)->startOfWeek()->toDateString())->count();
        $averageAttendance = $weeksWithData > 0 ? round($totalAttendance / $weeksWithData) : 0;

        return [
            Stat::make('Total Members', Member::active()->count()),
            Stat::make('Total Groups', Group::active()->count()),
            Stat::make('Active Leaders', Leader::where('is_active', true)->count()),
            Stat::make('Avg Weekly Attendance (4wk)', $averageAttendance),
        ];
    }
}
