<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\NonMemberAttendance;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function submitAttendance(int $groupId, string $date, array $attendances, int $leaderId, array $nonMemberAttendances = []): AttendanceSummary
    {
        return DB::transaction(function () use ($groupId, $date, $attendances, $leaderId, $nonMemberAttendances) {
            $memberAttendedCount = collect($attendances)->where('attended', true)->count();
            $nonMemberAttendedCount = collect($nonMemberAttendances)->where('attended', true)->count();

            $summary = AttendanceSummary::create([
                'group_id' => $groupId,
                'date' => $date,
                'submitted_by_leader_id' => $leaderId,
                'total_attendance' => $memberAttendedCount + $nonMemberAttendedCount,
                'visitor_count' => collect($attendances)->where('is_visitor', true)->count() + $nonMemberAttendedCount,
                'first_timer_count' => collect($attendances)->where('is_first_timer', true)->count()
                    + collect($nonMemberAttendances)->where('is_first_timer', true)->count(),
            ]);

            foreach ($attendances as $attendance) {
                Attendance::create([
                    'attendance_summary_id' => $summary->id,
                    'member_id' => $attendance['member_id'],
                    'attended' => $attendance['attended'] ?? false,
                    'is_first_timer' => $attendance['is_first_timer'] ?? false,
                    'is_visitor' => $attendance['is_visitor'] ?? false,
                    'is_new_convert' => $attendance['is_new_convert'] ?? false,
                ]);
            }

            foreach ($nonMemberAttendances as $nma) {
                NonMemberAttendance::create([
                    'attendance_summary_id' => $summary->id,
                    'non_member_id' => $nma['non_member_id'],
                    'attended' => $nma['attended'] ?? true,
                    'is_first_timer' => $nma['is_first_timer'] ?? false,
                    'is_new_convert' => $nma['is_new_convert'] ?? false,
                ]);
            }

            return $summary->load('attendances', 'nonMemberAttendances.nonMember');
        });
    }

    public function updateAttendance(int $summaryId, array $attendances): AttendanceSummary
    {
        return DB::transaction(function () use ($summaryId, $attendances) {
            $summary = AttendanceSummary::findOrFail($summaryId);
            $summary->attendances()->delete();

            foreach ($attendances as $attendance) {
                Attendance::create([
                    'attendance_summary_id' => $summary->id,
                    'member_id' => $attendance['member_id'],
                    'attended' => $attendance['attended'] ?? false,
                    'is_first_timer' => $attendance['is_first_timer'] ?? false,
                    'is_visitor' => $attendance['is_visitor'] ?? false,
                    'is_new_convert' => $attendance['is_new_convert'] ?? false,
                ]);
            }

            $summary->update([
                'total_attendance' => collect($attendances)->where('attended', true)->count(),
                'visitor_count' => collect($attendances)->where('is_visitor', true)->count(),
                'first_timer_count' => collect($attendances)->where('is_first_timer', true)->count(),
            ]);

            return $summary->fresh('attendances');
        });
    }

    public function deleteAttendance(int $summaryId): void
    {
        AttendanceSummary::findOrFail($summaryId)->delete();
    }

    public function getHistory(int $groupId, ?string $startDate = null, ?string $endDate = null, int $perPage = 15)
    {
        $query = AttendanceSummary::where('group_id', $groupId)
            ->with('submittedBy.member')
            ->orderByDesc('date');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    public function getDefaulters(int $parentGroupId, string $date): array
    {
        $childGroups = Group::where('parent_id', $parentGroupId)
            ->where('is_active', true)
            ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
            ->get();

        $submittedGroupIds = AttendanceSummary::where('date', $date)
            ->whereIn('group_id', $childGroups->pluck('id'))
            ->pluck('group_id')
            ->toArray();

        return $childGroups
            ->filter(fn ($group) => !in_array($group->id, $submittedGroupIds))
            ->values()
            ->toArray();
    }
}
