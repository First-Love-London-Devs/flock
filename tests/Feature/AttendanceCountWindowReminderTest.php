<?php

namespace Tests\Feature;

use App\Models\AttendanceCounter;
use App\Models\AttendanceSchedule;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\RoleDefinition;
use App\Services\AttendanceReminderService;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class AttendanceCountWindowReminderTest extends TestCase
{
    private Group $stream;

    private RoleDefinition $role;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'church.timezone' => 'Europe/London',
            'church.attendance_day' => 0, // Sunday
            'church.attendance_reminder_window_hours' => 3,
        ]);

        $type = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 1,
            'tracks_attendance' => true, 'is_active' => true,
        ]);
        $this->stream = Group::create(['name' => 'FLL Stream', 'group_type_id' => $type->id, 'parent_id' => null]);
        $this->role = RoleDefinition::create([
            'name' => 'Attendance Lead', 'slug' => 'attendance-lead',
            'permission_level' => 80, 'is_active' => true,
        ]);

        // An active schedule the reminder role is derived from ("the role your
        // reminders use now"); its day/window is irrelevant on the attendance day.
        AttendanceSchedule::create([
            'stream_group_id' => $this->stream->id, 'role_definition_id' => $this->role->id,
            'day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '10:00', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A Sunday (2026-07-26 is a Sunday) at the given local hour. */
    private function sundayAt(int $hour): Carbon
    {
        return Carbon::create(2026, 7, 26, $hour, 0, 0, 'Europe/London');
    }

    private function makeCounter(Group $group, int $createdHoursAgo, int $firstTime = 10): AttendanceCounter
    {
        $counter = AttendanceCounter::create([
            'group_id' => $group->id,
            'date' => Carbon::now('Europe/London')->toDateString(),
            'first_time_count' => $firstTime,
            'returning_count' => 0, 'regular_count' => 0, 'visitor_count' => 0,
        ]);
        $counter->forceFill(['created_at' => Carbon::now()->subHours($createdHoursAgo)])->saveQuietly();

        return $counter;
    }

    private function mockPush(): Mockery\MockInterface
    {
        $push = Mockery::mock(PushNotificationService::class);
        $this->app->instance(PushNotificationService::class, $push);

        return $push;
    }

    private function sendReminders(): array
    {
        return $this->app->make(AttendanceReminderService::class)->sendDueReminders();
    }

    public function test_sends_within_the_window_from_first_count(): void
    {
        Carbon::setTestNow($this->sundayAt(10));   // first count was 1h ago
        $this->makeCounter($this->stream, 1);

        $push = $this->mockPush();
        $push->shouldReceive('sendToRoleHoldersInGroup')
            ->once()
            ->with('attendance-lead', $this->stream->id, Mockery::type('string'), Mockery::type('string'), Mockery::type('array'))
            ->andReturn(['success' => true]);

        $this->assertSame(1, $this->sendReminders()['sent']);
    }

    public function test_silent_after_the_three_hour_window_closes(): void
    {
        Carbon::setTestNow($this->sundayAt(14));   // first count 4h ago
        $this->makeCounter($this->stream, 4);

        $push = $this->mockPush();
        $push->shouldReceive('sendToRoleHoldersInGroup')->never();

        $this->assertSame(0, $this->sendReminders()['sent']);
    }

    public function test_silent_before_any_count(): void
    {
        Carbon::setTestNow($this->sundayAt(10));
        $this->makeCounter($this->stream, 1, firstTime: 0); // total 0

        $push = $this->mockPush();
        $push->shouldReceive('sendToRoleHoldersInGroup')->never();

        $this->assertSame(0, $this->sendReminders()['sent']);
    }

    public function test_fires_for_a_stream_with_no_schedule_of_its_own(): void
    {
        // Regardless of whether that stream has a weekly schedule.
        $type = GroupType::where('slug', 'stream')->first();
        $other = Group::create(['name' => 'Second Stream', 'group_type_id' => $type->id, 'parent_id' => null]);

        Carbon::setTestNow($this->sundayAt(10));
        $this->makeCounter($other, 1); // only $other has a count today

        $push = $this->mockPush();
        $push->shouldReceive('sendToRoleHoldersInGroup')
            ->once()
            ->with('attendance-lead', $other->id, Mockery::type('string'), Mockery::type('string'), Mockery::type('array'))
            ->andReturn(['success' => true]);

        $this->assertSame(1, $this->sendReminders()['sent']);
    }

    public function test_non_attendance_day_uses_the_scheduled_window(): void
    {
        // Wednesday: the Sunday count-window path must not run; the scheduled
        // path applies and this stream's schedule is for Sunday, so nothing fires.
        Carbon::setTestNow(Carbon::create(2026, 7, 29, 9, 0, 0, 'Europe/London')); // Wednesday
        $this->makeCounter($this->stream, 1);

        $push = $this->mockPush();
        $push->shouldReceive('sendToRoleHoldersInGroup')->never();

        $this->assertSame(0, $this->sendReminders()['sent']);
    }
}
