<?php

namespace App\Filament\Resources\AttendanceCounterResource\Pages;

use App\Filament\Resources\AttendanceCounterResource;
use App\Models\AttendanceCounter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceCounters extends ListRecords
{
    protected static string $resource = AttendanceCounterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['Date', 'Stream', 'First time', 'Returning', 'Regular', 'Visitor', 'Total', 'Last reset', 'Last tap']);

                        AttendanceCounter::with('group')->orderByDesc('date')->chunk(200, function ($rows) use ($out) {
                            foreach ($rows as $row) {
                                fputcsv($out, [
                                    optional($row->date)->toDateString(),
                                    $row->group?->name,
                                    $row->first_time_count,
                                    $row->returning_count,
                                    $row->regular_count,
                                    $row->visitor_count,
                                    $row->total_count,
                                    optional($row->reset_at)->toDateTimeString(),
                                    optional($row->updated_at)->toDateTimeString(),
                                ]);
                            }
                        });

                        fclose($out);
                    }, 'attendance-counts.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}
