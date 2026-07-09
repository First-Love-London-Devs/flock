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
     * Fire any attendance-counter reminders that are due right now for the
     * current tenant. Sends at most one push per schedule per day, and only
     * while that Stream's counter is still empty for today.
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
            // Inside the window?
            $start = $this->toMinutes($schedule->start_time);
            $end = $this->toMinutes($schedule->end_time);
            if ($start === null || $end === null || $nowMinutes < $start || $nowMinutes > $end) {
                continue;
            }

            // Already nudged for this service today?
            if (AttendanceCounterNotification::hasBeenSent($schedule->id, $today)) {
                $skipped++;

                continue;
            }

            // Counter already used today? Then there is nothing to remind about.
            $counter = AttendanceCounter::where('group_id', $schedule->stream_group_id)
                ->whereDate('date', $today)
                ->first();
            if ($counter && $counter->total_count > 0) {
                $skipped++;

                continue;
            }

            $role = $schedule->roleDefinition;
            if (! $role) {
                $skipped++;

                continue;
            }

            $streamName = $schedule->streamGroup?->name ?? 'Your service';

            $result = $this->push->sendToRoleHoldersInGroup(
                $role->slug,
                $schedule->stream_group_id,
                'Time to count 🙌',
                $streamName.' is on now. Log today\'s head count in the attendance counter.',
                [
                    'type' => 'attendance_counter_reminder',
                    'streamGroupId' => $schedule->stream_group_id,
                ],
            );

            // Stamp the ledger whether or not anyone had a token, so we make one
            // attempt per service window and never re-fire on the next tick.
            AttendanceCounterNotification::create([
                'attendance_schedule_id' => $schedule->id,
                'stream_group_id' => $schedule->stream_group_id,
                'notification_date' => $today,
                'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);

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
