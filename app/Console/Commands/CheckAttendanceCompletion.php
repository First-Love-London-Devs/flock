<?php

namespace App\Console\Commands;

use App\Models\AttendanceNotification;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Tenant;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class CheckAttendanceCompletion extends Command
{
    protected $signature = 'attendance:check-completion {--date=}';
    protected $description = 'Check if all groups have submitted attendance and notify parent leaders';

    public function handle(PushNotificationService $pushService): int
    {
        $date = $this->option('date') ?? now()->toDateString();

        $processForTenant = function () use ($date, $pushService) {
            // Get all parent groups that have children tracking attendance
            $parentGroups = Group::active()
                ->whereHas('children', function ($q) {
                    $q->where('is_active', true)
                        ->whereHas('groupType', fn ($gt) => $gt->where('tracks_attendance', true));
                })
                ->get();

            foreach ($parentGroups as $parent) {
                if (AttendanceNotification::hasBeenSent($parent->id, $date, 'completion')) {
                    continue;
                }

                $childGroups = $parent->children()
                    ->where('is_active', true)
                    ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
                    ->get();

                $totalChildren = $childGroups->count();
                if ($totalChildren === 0) continue;

                $submitted = AttendanceSummary::where('date', $date)
                    ->whereIn('group_id', $childGroups->pluck('id'))
                    ->count();

                if ($submitted >= $totalChildren) {
                    $totalAttendance = AttendanceSummary::where('date', $date)
                        ->whereIn('group_id', $childGroups->pluck('id'))
                        ->sum('total_attendance');

                    if ($parent->leader_id) {
                        $pushService->sendToLeader(
                            $parent->leader_id,
                            'All Attendance Submitted!',
                            "All {$totalChildren} groups under {$parent->name} have submitted. Total attendance: {$totalAttendance}",
                            ['type' => 'attendance_completion', 'group_id' => $parent->id]
                        );
                    }

                    AttendanceNotification::markAsSent($parent->id, $date, $totalAttendance, $parent->leader_id);
                    $this->info("Notified: {$parent->name} - {$submitted}/{$totalChildren} submitted, total: {$totalAttendance}");
                }
            }
        };

        if (tenant()) {
            $processForTenant();
        } else {
            $tenants = Tenant::where('is_active', true)->get();
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($processForTenant, $tenant) {
                    $this->info("Processing: {$tenant->church_name}");
                    $processForTenant();
                });
            }
        }

        return self::SUCCESS;
    }
}
