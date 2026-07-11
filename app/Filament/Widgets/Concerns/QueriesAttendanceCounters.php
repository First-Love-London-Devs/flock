<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\AttendanceCounter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query for the attendance-analytics widgets. Reads the dashboard
 * filters (fromDate / toDate / streamId) exposed by InteractsWithPageFilters
 * and scopes the attendance_counters rows accordingly.
 */
trait QueriesAttendanceCounters
{
    protected function counterQuery(): Builder
    {
        $filters = $this->filters ?? [];

        return AttendanceCounter::query()
            ->with('group')
            ->when($filters['fromDate'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['toDate'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['streamId'] ?? null, fn (Builder $q, $v) => $q->where('group_id', $v));
    }
}
