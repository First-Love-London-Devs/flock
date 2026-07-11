<?php

namespace App\Services;

use App\Models\AttendanceCounter;
use App\Models\AttendanceCounterNotification;
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
     * Fire any attendance-counter summaries that are due right now for the
     * current tenant. Once a service window has ended, the role holders get a
     * single push with the head count that was taken for that service. Sends
     * at most one push per schedule per day, and only when a count was
     * actually recorded (an empty service produces no notification).
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
            // Only summarise once the service window has ended.
            $end = $this->toMinutes($schedule->end_time);
            if ($end === null || $nowMinutes < $end) {
                continue;
            }

            // Already summarised (or evaluated) for this service today?
            if (AttendanceCounterNotification::hasBeenSent($schedule->id, $today)) {
                $skipped++;

                continue;
            }

            $role = $schedule->roleDefinition;
            if (! $role) {
                // Record that we evaluated it so we don't re-check every tick.
                $this->stampLedger($schedule, $today, 'skipped');
                $skipped++;

                continue;
            }

            $counter = AttendanceCounter::where('group_id', $schedule->stream_group_id)
                ->whereDate('date', $today)
                ->first();
            $total = $counter ? $counter->total_count : 0;

            // Nothing was counted for this service — no summary to send. Stamp
            // the ledger so the empty service isn't re-checked on later ticks.
            if ($total <= 0) {
                $this->stampLedger($schedule, $today, 'skipped');
                $skipped++;

                continue;
            }

            $streamName = $schedule->streamGroup?->name ?? 'Your service';

            $body = sprintf(
                '%s: %d counted. First-time %d, returning %d, regular %d, visitor %d.',
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

            $this->stampLedger($schedule, $today, ($result['success'] ?? false) ? 'sent' : 'failed');

            ($result['success'] ?? false) ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function stampLedger(AttendanceSchedule $schedule, string $today, string $status): void
    {
        AttendanceCounterNotification::create([
            'attendance_schedule_id' => $schedule->id,
            'stream_group_id' => $schedule->stream_group_id,
            'notification_date' => $today,
            'status' => $status,
            'sent_at' => now(),
        ]);
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
