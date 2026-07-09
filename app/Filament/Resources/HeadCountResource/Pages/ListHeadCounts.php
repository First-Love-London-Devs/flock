<?php

namespace App\Filament\Resources\HeadCountResource\Pages;

use App\Filament\Resources\HeadCountResource;
use App\Models\HeadCount;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListHeadCounts extends ListRecords
{
    protected static string $resource = HeadCountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['Date', 'Bacenta', 'Total', 'First-timers', 'Visitors', 'Submitted by', 'Submitted at']);

                        HeadCount::with('group')->orderByDesc('date')->chunk(200, function ($rows) use ($out) {
                            foreach ($rows as $row) {
                                fputcsv($out, [
                                    optional($row->date)->toDateString(),
                                    $row->group?->name,
                                    $row->total_attendance,
                                    $row->first_timer_count,
                                    $row->visitor_count,
                                    $row->submitter_name,
                                    optional($row->created_at)->toDateTimeString(),
                                ]);
                            }
                        });

                        fclose($out);
                    }, 'head-counts.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}
