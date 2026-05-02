<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'attendance_summary_id' => AttendanceSummary::factory(),
            'member_id' => Member::factory(),
            'attended' => true,
            'is_first_timer' => false,
            'is_visitor' => false,
            'is_new_convert' => false,
        ];
    }
}
