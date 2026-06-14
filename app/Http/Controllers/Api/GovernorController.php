<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernorController extends Controller
{
    public function __construct(private readonly ConstituencyAnalytics $service) {}

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
        if (! $detail) {
            return response()->json(['success' => false, 'message' => 'group not found'], 404);
        }

        return $this->ok($detail);
    }

    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $search = $request->query('search');

        return $this->ok($this->service->members($this->constituency($request), $perPage, $search));
    }

    public function attendanceTrend(Request $request): JsonResponse
    {
        $weeks = max(1, min(26, (int) $request->query('weeks', 8)));
        return $this->ok($this->service->attendanceTrend($this->constituency($request), $weeks));
    }

    public function attendancePulse(Request $request): JsonResponse
    {
        return $this->ok($this->service->attendancePulse($this->constituency($request)));
    }

    public function firstTimers(Request $request): JsonResponse
    {
        $daysBack = max(7, min(90, (int) $request->query('days', 28)));
        return $this->ok($this->service->firstTimers($this->constituency($request), $daysBack));
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->ok($this->service->attendance(
            $this->constituency($request),
            $this->serviceType($request),
            $this->serviceDate($request),
        ));
    }

    protected function constituency(Request $request): Group
    {
        // Generic oversight: governor (constituency) and ministry-head share
        // this rollup flow — both oversee the child groups of their assigned
        // group. ConstituencyAnalytics keys purely off parent_id, so it's
        // group-type agnostic.
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->whereRaw('LOWER(slug) IN (?, ?, ?, ?)', ['governor', 'basonta-head', 'basonta-overseer', 'ministry-head']))
            ->with('group')
            ->first();

        if (! $role || ! $role->group) {
            abort(response()->json(['success' => false, 'message' => 'no oversight group assigned'], 403));
        }

        return $role->group;
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
