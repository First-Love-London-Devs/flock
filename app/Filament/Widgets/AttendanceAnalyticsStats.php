<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\QueriesAttendanceCounters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

/**
 * Headline attendance-counter numbers for the Attendance Analytics page.
 * Hidden from the default dashboard (canView false); rendered explicitly by
 * the page so it can be fed the page filters.
 */
class AttendanceAnalyticsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use QueriesAttendanceCounters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        if (! Schema::hasTable('attendance_counters')) {
            return [Stat::make('Attendance', 'n/a')];
        }

        $rows = $this->counterQuery()->get();

        $serviceCounts = $rows->count();
        $total = $rows->sum(fn ($r) => $r->total_count);
        $average = $serviceCounts ? round($total / $serviceCounts) : 0;
        $days = $rows->pluck('date')->map->toDateString()->unique()->count();

        $peakRow = $rows->sortByDesc(fn ($r) => $r->total_count)->first();
        $peak = $peakRow ? $peakRow->total_count : 0;
        $peakLabel = $peakRow
            ? (($peakRow->group->name ?? 'Stream').' · '.optional($peakRow->date)->format('d M Y'))
            : 'No data yet';

        return [
            Stat::make('Total counted', number_format($total))
                ->description($serviceCounts.' service count'.($serviceCounts === 1 ? '' : 's'))
                ->color('primary'),
            Stat::make('Average per service', number_format($average))
                ->description('Across '.$days.' day'.($days === 1 ? '' : 's')),
            Stat::make('Peak service', number_format($peak))
                ->description($peakLabel),
        ];
    }
}
