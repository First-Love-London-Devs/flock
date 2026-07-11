<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceAnalyticsLeague;
use App\Filament\Widgets\AttendanceAnalyticsStats;
use App\Filament\Widgets\AttendanceAnalyticsTrend;
use App\Models\AttendanceCounter;
use App\Models\Group;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AttendanceAnalytics extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Analytics';

    protected static ?string $title = 'Attendance Analytics';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.attendance-analytics';

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('attendance_counters');
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('fromDate')
                ->label('From')
                ->default(now()->subDays(90)->startOfDay())
                ->native(false),
            DatePicker::make('toDate')
                ->label('To')
                ->default(now()->endOfDay())
                ->native(false),
            Select::make('streamId')
                ->label('Stream')
                ->options(fn () => $this->streamOptions())
                ->placeholder('All streams'),
        ]);
    }

    /**
     * @return array<int, class-string>
     */
    public function analyticsWidgets(): array
    {
        return [
            AttendanceAnalyticsStats::class,
            AttendanceAnalyticsTrend::class,
            AttendanceAnalyticsLeague::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function streamOptions(): array
    {
        if (! Schema::hasTable('groups')) {
            return [];
        }

        return Group::query()
            ->whereHas('groupType', fn ($q) => $q->where('slug', 'stream'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $query = $this->filteredQuery();

                    return response()->streamDownload(function () use ($query) {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['Date', 'Stream', 'First time', 'Returning', 'Regular', 'Visitor', 'Total']);

                        $query->orderBy('date')->orderBy('group_id')->chunk(200, function ($rows) use ($out) {
                            foreach ($rows as $row) {
                                fputcsv($out, [
                                    optional($row->date)->toDateString(),
                                    $row->group?->name,
                                    $row->first_time_count,
                                    $row->returning_count,
                                    $row->regular_count,
                                    $row->visitor_count,
                                    $row->total_count,
                                ]);
                            }
                        });

                        fclose($out);
                    }, 'attendance-analytics-'.Carbon::now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }

    protected function filteredQuery(): Builder
    {
        $filters = $this->filters ?? [];

        return AttendanceCounter::query()
            ->with('group')
            ->when($filters['fromDate'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['toDate'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['streamId'] ?? null, fn (Builder $q, $v) => $q->where('group_id', $v));
    }
}
