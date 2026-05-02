<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernorController extends Controller
{
    public function __construct(private readonly ConstituencyAnalytics $service)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->ok($this->service->dashboard($this->constituency($request)));
    }

    public function groups(Request $request): JsonResponse
    {
        return $this->ok($this->service->groups($this->constituency($request)));
    }

    public function groupDetail(Request $request, int $id): JsonResponse
    {
        $detail = $this->service->groupDetail($this->constituency($request), $id);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'group not found'], 404);
        }
        return $this->ok($detail);
    }

    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return $this->ok($this->service->members($this->constituency($request), $perPage));
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->ok($this->service->attendance(
            $this->constituency($request),
            $this->dateRange($request),
        ));
    }

    protected function constituency(Request $request): Group
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
            ->with('group')
            ->first();

        if (!$role || !$role->group) {
            abort(response()->json(['success' => false, 'message' => 'no constituency assigned'], 403));
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
