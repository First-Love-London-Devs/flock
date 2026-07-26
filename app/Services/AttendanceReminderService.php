<?php

namespace App\Services;

use App\Models\AttendanceCounter;
use App\Models\AttendanceSchedule;
use App\Models\RoleDefinition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceReminderService
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * The current church's local timezone. Service windows are entered in the
     * church's own local time (e.g. a Belgian church uses Europe/Brussels), so
     * we compare "now" in that zone rather than a hardcoded one. Falls back to
     * the app-wide church default when there is no tenant context.
     */
    private function timezone(): string
    {
        return tenant()?->getTimezone() ?? config('church.timezone', 'Europe/London');
    }

    /**
     * Fire attendance-counter summaries that are due right now for the current
     * tenant. On the church's attendance day (Sunday by default) this runs
     * independently of any weekly schedule; on other days it falls back to the
     * configured schedules. Either way role holders get a running head-count
     * summary on every 30-minute tick (the command is scheduled every 30
     * minutes) while the window is open, so they see the number climb.
     *
     * @return array{sent:int, skipped:int}
     */
    public function sendDueReminders(): array
    {
        // Tolerate the window between a code deploy and `tenants:migrate`.
        if (! Schema::hasTable('attendance_schedules') || ! Schema::hasTable('attendance_counters')) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $now = Carbon::now($this->timezone());

        // On the attendance day, ignore the weekly schedule entirely: any stream
        // that has started counting today gets reminders every 30 minutes from
        // its first count until the window closes.
        if ($now->dayOfWeek === (int) config('church.attendance_day', Carbon::SUNDAY)) {
            return $this->sendCountWindowReminders($now);
        }

        return $this->sendScheduledReminders($now);
    }

    /**
     * Schedule-independent path. For every stream with a count today, send a
     * running summary on each 30-minute tick within a window that opens at the
     * first count (the counter row is created on the first tap) and lasts
     * `church.attendance_reminder_window_hours` hours. Recipients are the role
     * the church's reminders already use (see {@see reminderRole()}).
     *
     * @return array{sent:int, skipped:int}
     */
    private function sendCountWindowReminders(Carbon $now): array
    {
        $role = $this->reminderRole();
        if (! $role) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $windowHours = (int) config('church.attendance_reminder_window_hours', 3);
        $sent = 0;
        $skipped = 0;

        $counters = AttendanceCounter::whereDate('date', $now->toDateString())->with('group')->get();

        foreach ($counters as $counter) {
            if ($counter->total_count <= 0) {
                $skipped++;

                continue;
            }

            $start = $counter->created_at->copy()->setTimezone($this->timezone());
            $end = $start->copy()->addHours($windowHours);
            if ($now->lt($start) || $now->gt($end)) {
                $skipped++;

                continue;
            }

            $streamName = $counter->group?->name ?? 'Your service';
            $result = $this->push->sendToRoleHoldersInGroup(
                $role->slug,
                $counter->group_id,
                'Attendance summary 📊',
                $this->summaryBody($counter, $streamName),
                ['type' => 'attendance_summary', 'streamGroupId' => $counter->group_id],
            );

            ($result['success'] ?? false) ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Original schedule-driven path, used on non-attendance days: only while a
     * configured service window is open, and only for streams with a count.
     *
     * @return array{sent:int, skipped:int}
     */
    private function sendScheduledReminders(Carbon $now): array
    {
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
            if ($total <= 0) {
                $skipped++;

                continue;
            }

            $streamName = $schedule->streamGroup?->name ?? 'Your service';
            $result = $this->push->sendToRoleHoldersInGroup(
                $role->slug,
                $schedule->stream_group_id,
                'Attendance summary 📊',
                $this->summaryBody($counter, $streamName),
                ['type' => 'attendance_summary', 'streamGroupId' => $schedule->stream_group_id],
            );

            ($result['success'] ?? false) ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * The role the church's attendance reminders target: the most common role
     * across active schedules ("the role your reminders use now"), or a
     * configured fallback slug when there are no schedules at all.
     */
    private function reminderRole(): ?RoleDefinition
    {
        $roleId = AttendanceSchedule::where('is_active', true)
            ->groupBy('role_definition_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('role_definition_id');

        if ($roleId) {
            return RoleDefinition::find($roleId);
        }

        $slug = config('church.attendance_reminder_role');

        return $slug ? RoleDefinition::where('slug', $slug)->first() : null;
    }

    /** The running head-count summary line pushed to role holders. */
    private function summaryBody(AttendanceCounter $counter, string $streamName): string
    {
        return sprintf(
            '%s: %d counted so far. First-time %d, returning %d, regular %d, visitor %d.',
            $streamName,
            $counter->total_count,
            $counter->first_time_count,
            $counter->returning_count,
            $counter->regular_count,
            $counter->visitor_count,
        );
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
