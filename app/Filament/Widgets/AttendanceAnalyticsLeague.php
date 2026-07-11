<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\QueriesAttendanceCounters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

/**
 * Per-stream league table (ranked by total) with the category breakdown.
 * Hidden from the default dashboard; rendered by the Attendance Analytics page.
 */
class AttendanceAnalyticsLeague extends Widget
{
    use InteractsWithPageFilters;
    use QueriesAttendanceCounters;

    protected static string $view = 'filament.widgets.attendance-analytics-league';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        if (! Schema::hasTable('attendance_counters')) {
            return [];
        }

        return $this->counterQuery()->get()
            ->groupBy('group_id')
            ->map(function ($group) {
                $first = (int) $group->sum('first_time_count');
                $returning = (int) $group->sum('returning_count');
                $regular = (int) $group->sum('regular_count');
                $visitor = (int) $group->sum('visitor_count');
                $total = $first + $returning + $regular + $visitor;
                $services = $group->count();

                return [
                    'stream' => $group->first()->group->name ?? 'Stream',
                    'first' => $first,
                    'returning' => $returning,
                    'regular' => $regular,
                    'visitor' => $visitor,
                    'services' => $services,
                    'average' => $services ? (int) round($total / $services) : 0,
                    'total' => $total,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
