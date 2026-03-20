<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $groupId = $request->query('group_id') ? (int) $request->query('group_id') : null;
            $stats = $this->dashboardService->getStats($groupId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats.',
            ], 500);
        }
    }

    public function attendanceTrends(Request $request): JsonResponse
    {
        try {
            $groupId = $request->query('group_id') ? (int) $request->query('group_id') : null;
            $weeks = (int) $request->query('weeks', 8);

            $trends = $this->dashboardService->getAttendanceTrends($groupId, $weeks);

            return response()->json([
                'success' => true,
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance trends.',
            ], 500);
        }
    }

    public function defaulters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'date' => 'required|date',
        ]);

        try {
            $attendanceService = app(AttendanceService::class);
            $defaulters = $attendanceService->getDefaulters(
                (int) $validated['group_id'],
                $validated['date']
            );

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

    public function stats(Request $request): JsonResponse
    {
        return $this->index($request);
    }
}
