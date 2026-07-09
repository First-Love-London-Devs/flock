# Attendance counter reminders (periodic push) — design

Nudge the right people to submit a Stream's head count during its service, using
the tap-counter already shipped at `/attendance-counter/{stream}`. Modelled on
Poimen's `attendance-counter:send-notification`, generalised for Flock's
multi-tenant setup and made self-serve so staff configure their own service
windows.

## What already existed (reused, not rebuilt)

- `PushNotificationService` — POSTs to Expo (`https://exp.host/--/api/v2/push/send`).
- `push_tokens` (tenant table, keyed on `leader_id`) + `POST /api/v1/push-token`.
- The multi-tenant scheduling idiom: a command runs centrally, then loops
  `Tenant::where('is_active', true)` and re-runs its body via `$tenant->run(...)`.
- The dedup-ledger pattern (`BirthdayNotification::hasBeenSent`).

## New pieces

| File | Role |
|---|---|
| `database/migrations/tenant/2026_07_09_150000_create_attendance_reminder_tables.php` | `attendance_schedules` + `attendance_counter_notifications` ledger |
| `app/Models/AttendanceSchedule.php` | one service window (Stream + role + day + time range) |
| `app/Models/AttendanceCounterNotification.php` | sent-ledger, `hasBeenSent(scheduleId, date)` |
| `app/Services/PushNotificationService.php` | + `sendToRoleHoldersInGroup()` — role holders scoped to one Stream's subtree |
| `app/Services/AttendanceReminderService.php` | core per-tenant logic (`sendDueReminders`) |
| `app/Console/Commands/SendAttendanceCounterReminders.php` | `attendance-counter:remind`, tenant fan-out |
| `app/Console/Kernel.php` | schedules it `everyThirtyMinutes()->withoutOverlapping()` |
| `app/Filament/Resources/AttendanceScheduleResource.php` (+ 3 Pages) | admin CRUD for windows, under the "Attendance" nav group |

## Behaviour

Every 30 minutes the command loops active tenants. Per tenant, for each active
schedule whose `day_of_week` is today and whose `[start_time, end_time]` window
contains **now** (compared in `Europe/London`, since the app tz is UTC):

1. Skip if a reminder was already sent for this schedule today (unique ledger row
   on `attendance_schedule_id + notification_date`).
2. Skip if that Stream's counter already has a total > 0 for today — nothing to
   remind about.
3. Otherwise push `sendToRoleHoldersInGroup(role.slug, streamGroupId, …)` and
   write the ledger row (status `sent`/`failed`) regardless of whether anyone had
   a token — one attempt per service window, never re-fires on the next tick.

Net: **one timely reminder per service, self-silencing the moment a count is
tapped in.** Inert until a tenant creates a schedule row.

## Decisions

- **Windows: admin-editable** (Filament), not config — staff set their own times.
- **Recipients: a specific role**, scoped to the Stream's subtree (not tenant-wide),
  chosen from the tenant's real roles in the form so it never targets zero people
  by accident.
- **Cadence: once per service**, not a Poimen-style every-30-min barrage. A
  "remind again while still empty" toggle is deliberately left for later.

## Known simplifications (v2 candidates)

- Windows are evaluated in `Europe/London`; fine for current UK tenants, would
  need per-tenant tz if that changes.
- `QUEUE_CONNECTION=sync` → Expo POSTs run inline in the scheduler (fine at this
  scale). The send path has no Expo batching/receipt handling yet.
- Two services for one Stream on the same day share one daily counter row, so the
  zero-count guard is per-day, not per-service.

## Deploy

Merge to `main` (Forge Quick Deploy) → **`php artisan tenants:migrate --force`**
(creates the two tenant tables) → the every-30-min cron picks it up. Staff then
add a schedule under Admin → Attendance → Attendance Reminders.
