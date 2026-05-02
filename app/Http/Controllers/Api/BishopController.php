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
            $this->dateRange($request),
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
        $role = LeaderRole::where('leader_id', $govId)
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
            ->with('group')
            ->first();

        if (!$role || !$role->group) {
            abort(response()->json(['success' => false, 'message' => 'governor not found'], 404));
        }
        return $role->group;
    }

    protected function dateRange(Request $request): CarbonPeriod
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::now()->startOfWeek();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : Carbon::now()->endOfWeek();
        return CarbonPeriod::create($from, $to);
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
