<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSummary;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class AttendanceTrendsChart extends ChartWidget
{
    protected static ?string $heading = 'Weekly Attendance Trends';

    protected function getData(): array
    {
        $weeks = collect();
        $labels = collect();

        for ($i = 7; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $total = AttendanceSummary::whereBetween('date', [$weekStart, $weekEnd])
                ->sum('total_attendance');

            $weeks->push($total);
            $labels->push($weekStart->format('M d'));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Attendance',
                    'data' => $weeks->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
