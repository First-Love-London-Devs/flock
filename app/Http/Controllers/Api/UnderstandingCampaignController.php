<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\UnderstandingCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnderstandingCampaignController extends Controller
{
    /**
     * The gathering-service group this rep is scoped to, resolved from their
     * own understanding-campaign role rather than the union of all their roles.
     */
    protected function resolveScopeGroup(Request $request): Group
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['understanding-campaign']))
            ->with('group')
            ->first();

        if (! $role || ! $role->group) {
            abort(response()->json(['success' => false, 'message' => 'no gathering service assigned'], 403));
        }

        return $role->group;
    }

    public function index(Request $request): JsonResponse
    {
        $scopeIds = $this->resolveScopeGroup($request)->allGroupIds();

        $query = UnderstandingCampaign::query()
            ->whereIn('stream_id', $scopeIds)
            ->with(['stream:id,name', 'allocatedGroup:id,name'])
            ->orderByDesc('attended_on')
            ->orderByDesc('id');

        if ($request->query('status') === 'unassigned') {
            $query->whereNull('allocated_group_id');
        } elseif ($request->query('status') === 'assigned') {
            $query->whereNotNull('allocated_group_id');
        }

        $data = $query->get()->map(fn (UnderstandingCampaign $r) => [
            'id' => $r->id,
            'first_name' => $r->first_name,
            'last_name' => $r->last_name,
            'first_time' => (bool) $r->first_time,
            're_dedicating' => (bool) $r->re_dedicating,
            'attended_on' => optional($r->attended_on)->toDateString(),
            'who_invited' => $r->who_invited,
            'phone_number' => $r->phone_number,
            'stream' => $r->stream ? ['id' => $r->stream->id, 'name' => $r->stream->name] : null,
            'allocated_group' => $r->allocatedGroup ? ['id' => $r->allocatedGroup->id, 'name' => $r->allocatedGroup->name] : null,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function assignableGroups(Request $request): JsonResponse
    {
        $scopeIds = $this->resolveScopeGroup($request)->allGroupIds();

        $groups = Group::query()
            ->whereIn('id', $scopeIds)
            ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name]);

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
