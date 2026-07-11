<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\QueriesAttendanceCounters;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance-counter totals per service date, one bar series per stream.
 * Hidden from the default dashboard; rendered by the Attendance Analytics page.
 */
class AttendanceAnalyticsTrend extends ChartWidget
{
    use InteractsWithPageFilters;
    use QueriesAttendanceCounters;

    protected static ?string $heading = 'Attendance by service';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        return false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        if (! Schema::hasTable('attendance_counters')) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = $this->counterQuery()->get();

        $dates = $rows->pluck('date')->map->toDateString()->unique()->sort()->values();
        $labels = $dates->map(fn ($d) => Carbon::parse($d)->format('d M'))->toArray();

        $palette = ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed'];

        $datasets = [];
        $i = 0;
        foreach ($rows->groupBy('group_id') as $groupId => $group) {
            $name = $group->first()->group->name ?? ('Stream '.$groupId);
            $byDate = $group->keyBy(fn ($r) => $r->date->toDateString());
            $data = $dates->map(fn ($d) => isset($byDate[$d]) ? $byDate[$d]->total_count : 0)->toArray();
            $color = $palette[$i % count($palette)];

            $datasets[] = [
                'label' => $name,
                'data' => $data,
                'backgroundColor' => $color,
                'borderColor' => $color,
            ];
            $i++;
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }
}
