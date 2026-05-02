<?php

namespace Database\Factories;

use App\Models\AttendanceSummary;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceSummaryFactory extends Factory
{
    protected $model = AttendanceSummary::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'date' => now()->startOfWeek()->next('Sunday')->toDateString(),
            'total_attendance' => $this->faker->numberBetween(20, 80),
            'visitor_count' => 0,
            'first_timer_count' => 0,
            'submitted_by_leader_id' => null,
            'notes' => null,
        ];
    }

    public function onSunday(\DateTimeInterface $week = null): self
    {
        $sunday = ($week ? \Carbon\Carbon::instance($week) : now())->startOfWeek()->next('Sunday');
        return $this->state(['date' => $sunday->toDateString()]);
    }

    public function onWednesday(\DateTimeInterface $week = null): self
    {
        $wednesday = ($week ? \Carbon\Carbon::instance($week) : now())->startOfWeek()->next('Wednesday');
        return $this->state(['date' => $wednesday->toDateString()]);
    }
}
