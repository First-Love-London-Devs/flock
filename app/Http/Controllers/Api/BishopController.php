<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BishopController extends Controller
{
    public function __construct(private readonly ConstituencyAnalytics $service)
    {
    }

    public function governors(): JsonResponse
    {
        return $this->ok($this->service->constituencySummaries());
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->ok($this->service->tenantWideAttendance($this->dateRange($request)));
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->ok($this->service->tenantWideSummary(
            $this->serviceType($request),
            $this->serviceDate($request),
        ));
    }

    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return $this->ok($this->service->tenantWideMembers($perPage));
    }

    public function governorDashboard(int $govId): JsonResponse
    {
        return $this->ok($this->service->dashboard($this->constituencyForGovernor($govId)));
    }

    public function governorGroups(int $govId): JsonResponse
    {
        return $this->ok($this->service->groups($this->constituencyForGovernor($govId)));
    }

    public function governorAttendance(Request $request, int $govId): JsonResponse
    {
        return $this->ok($this->service->attendance(
            $this->constituencyForGovernor($govId),
            $this->serviceType($request),
            $this->serviceDate($request),
        ));
    }

    public function groupDetail(int $govId, int $groupId): JsonResponse
    {
        $detail = $this->service->groupDetail($this->constituencyForGovernor($govId), $groupId);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'group not found'], 404);
        }
        return $this->ok($detail);
    }

    protected function constituencyForGovernor(int $govId): Group
    {
        // The Bishop's Governors list returns one row per Constituency Group, so the
        // mobile drill-down passes the Constituency Group id as {govId}. Resolve to
        // that Group directly.
        $constituencyTypeIds = \App\Models\GroupType::whereIn('slug', ['constituency', 'governor'])->pluck('id');

        $group = Group::where('id', $govId)
            ->whereIn('group_type_id', $constituencyTypeIds)
            ->where('is_active', true)
            ->first();

        if (!$group) {
            abort(response()->json(['success' => false, 'message' => 'governor not found'], 404));
        }
        return $group;
    }

    protected function dateRange(Request $request): CarbonPeriod
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::now()->startOfWeek();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : Carbon::now()->endOfWeek();
        return CarbonPeriod::create($from, $to);
    }

    protected function serviceType(Request $request): string
    {
        return $request->query('service_type') === 'midweek' ? 'midweek' : 'sunday';
    }

    protected function serviceDate(Request $request): ?Carbon
    {
        $value = $request->query('date');
        return $value ? Carbon::parse($value) : null;
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
