<?php

namespace App\Services;

use App\Models\AttendanceCounter;
use App\Models\AttendanceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceReminderService
{
    /**
     * Service windows are entered in UK local time; the app default tz is UTC.
     */
    private const TIMEZONE = 'Europe/London';

    public function __construct(private PushNotificationService $push) {}

    /**
     * Fire attendance-counter summaries that are due right now for the current
     * tenant. While a service window is open, the role holders get a running
     * head-count summary on every 30-minute tick (the command is scheduled
     * every 30 minutes), so they see the number climb through the service. A
     * window with no count yet produces no push.
     *
     * @return array{sent:int, skipped:int}
     */
    public function sendDueReminders(): array
    {
        // Tolerate the window between a code deploy and `tenants:migrate`.
        if (! Schema::hasTable('attendance_schedules')) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $now = Carbon::now(self::TIMEZONE);
        $today = $now->toDateString();
        $nowMinutes = $now->hour * 60 + $now->minute;

        $sent = 0;
        $skipped = 0;

        $schedules = AttendanceSchedule::query()
            ->where('is_active', true)
            ->where('day_of_week', $now->dayOfWeek) // 0 = Sunday ... 6 = Saturday
            ->with(['streamGroup', 'roleDefinition'])
            ->get();

        foreach ($schedules as $schedule) {
            // Only while the service window is open. One tick = one update, so
            // the every-30-minute schedule spaces the summaries out.
            $start = $this->toMinutes($schedule->start_time);
            $end = $this->toMinutes($schedule->end_time);
            if ($start === null || $end === null || $nowMinutes < $start || $nowMinutes > $end) {
                continue;
            }

            $role = $schedule->roleDefinition;
            if (! $role) {
                $skipped++;

                continue;
            }

            $counter = AttendanceCounter::where('group_id', $schedule->stream_group_id)
                ->whereDate('date', $today)
                ->first();
            $total = $counter ? $counter->total_count : 0;

            // Nothing has been counted yet — no running total to report.
            if ($total <= 0) {
                $skipped++;

                continue;
            }

            $streamName = $schedule->streamGroup?->name ?? 'Your service';

            $body = sprintf(
                '%s: %d counted so far. First-time %d, returning %d, regular %d, visitor %d.',
                $streamName,
                $total,
                $counter->first_time_count,
                $counter->returning_count,
                $counter->regular_count,
                $counter->visitor_count,
            );

            $result = $this->push->sendToRoleHoldersInGroup(
                $role->slug,
                $schedule->stream_group_id,
                'Attendance summary 📊',
                $body,
                [
                    'type' => 'attendance_summary',
                    'streamGroupId' => $schedule->stream_group_id,
                ],
            );

            ($result['success'] ?? false) ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Minutes since midnight for a stored TIME value ("HH:MM" or "HH:MM:SS").
     */
    private function toMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        try {
            $t = Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time);
        } catch (\Throwable $e) {
            return null;
        }

        return $t->hour * 60 + $t->minute;
    }
}
