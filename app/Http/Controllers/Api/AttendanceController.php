<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Services\AttendanceService;
use App\Services\LeaderScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected LeaderScopeService $scope,
    ) {}

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.member_id' => 'required|exists:members,id',
            'attendances.*.attended' => 'required|boolean',
            'attendances.*.is_first_timer' => 'boolean',
            'attendances.*.is_visitor' => 'boolean',
        ]);

        if (!$this->scope->canAccessGroup($validated['group_id'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized for this group.'], 403);
        }

        try {
            $summary = $this->attendanceService->submitAttendance(
                $validated['group_id'],
                $validated['date'],
                $validated['attendances'],
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => $summary,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit attendance.',
            ], 500);
        }
    }

    public function update(Request $request, int $summaryId): JsonResponse
    {
        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.member_id' => 'required|exists:members,id',
            'attendances.*.attended' => 'required|boolean',
            'attendances.*.is_first_timer' => 'boolean',
            'attendances.*.is_visitor' => 'boolean',
        ]);

        try {
            $summary = $this->attendanceService->updateAttendance(
                $summaryId,
                $validated['attendances']
            );

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance summary not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance.',
            ], 500);
        }
    }

    public function destroy(int $summaryId): JsonResponse
    {
        try {
            $this->attendanceService->deleteAttendance($summaryId);

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance summary not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance.',
            ], 500);
        }
    }

    public function show(int $summaryId): JsonResponse
    {
        try {
            $summary = AttendanceSummary::with(['attendances.member', 'submittedBy.member', 'group'])
                ->findOrFail($summaryId);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance summary not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance.',
            ], 500);
        }
    }

    public function groupHistory(Request $request, int $groupId): JsonResponse
    {
        if (!$this->scope->canAccessGroup($groupId)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized for this group.'], 403);
        }

        try {
            $history = $this->attendanceService->getHistory(
                $groupId,
                $request->query('start_date'),
                $request->query('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance history.',
            ], 500);
        }
    }

    public function defaulters(int $parentGroupId, string $date): JsonResponse
    {
        if (!$this->scope->canAccessGroup($parentGroupId)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized for this group.'], 403);
        }

        try {
            $defaulters = $this->attendanceService->getDefaulters($parentGroupId, $date);

            return response()->json([
                'success' => true,
                'data' => $defaulters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch defaulters.',
            ], 500);
        }
    }
}
