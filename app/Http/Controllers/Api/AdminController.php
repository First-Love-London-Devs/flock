<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ─── Scope helpers ──────────────────────────────────────────────────────

    protected function adminGroupId(Request $request): int
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'admin'))
            ->first();

        abort_if(! $role, response()->json(['success' => false, 'message' => 'Admin group not assigned'], 403));
        return $role->group_id;
    }

    protected function scopedBacentaIds(Request $request): array
    {
        $rootId = $this->adminGroupId($request);
        $cellGroupTypeId = (int) GroupType::where('slug', 'cell-group')->value('id');

        // Include the root group itself if it is a cell-group.
        $rootGroup = Group::find($rootId, ['id', 'group_type_id']);
        $toVisit   = [];
        $bacentaIds = [];

        if ($rootGroup) {
            if ((int) $rootGroup->group_type_id === $cellGroupTypeId) {
                $bacentaIds[] = $rootGroup->id;
            } else {
                $toVisit[] = $rootId;
            }
        }

        while (! empty($toVisit)) {
            $children = Group::whereIn('parent_id', $toVisit)
                ->where('is_active', true)
                ->get(['id', 'group_type_id']);

            $toVisit = [];
            foreach ($children as $child) {
                if ((int) $child->group_type_id === $cellGroupTypeId) {
                    $bacentaIds[] = $child->id;
                } else {
                    $toVisit[] = $child->id;
                }
            }
        }

        return $bacentaIds;
    }

    protected function scopedBacenta(Request $request, int $id): Group
    {
        $ids = $this->scopedBacentaIds($request);
        abort_if(! in_array($id, $ids), response()->json(['success' => false, 'message' => 'Bacenta not in scope'], 403));
        return Group::findOrFail($id);
    }

    protected function scopedMember(Request $request, int $id): Member
    {
        $bacentaIds = $this->scopedBacentaIds($request);
        $member = Member::with('groups')->findOrFail($id);
        $inScope = $member->groups->pluck('id')->intersect($bacentaIds)->isNotEmpty();
        abort_if(! $inScope, response()->json(['success' => false, 'message' => 'Member not in scope'], 403));
        return $member;
    }

    // ─── Members ────────────────────────────────────────────────────────────

    public function listMembers(Request $request): JsonResponse
    {
        $bacentaIds = $this->scopedBacentaIds($request);
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 25);

        $query = Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $bacentaIds))
            ->with(['groups:id,name']);

        if ($search) {
            $query->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")
            );
        }

        return $this->ok($query->paginate($perPage));
    }

    public function showMember(Request $request, int $id): JsonResponse
    {
        return $this->ok($this->scopedMember($request, $id)->load('groups:id,name'));
    }

    public function createMember(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'phone_number'  => 'nullable|string|max:30',
            'gender'        => 'nullable|string|in:male,female',
            'date_of_birth' => 'nullable|date',
            'member_type'   => 'nullable|string|max:50',
            'bacenta_id'    => 'nullable|integer',
        ]);

        if (! empty($data['bacenta_id'])) {
            $this->scopedBacenta($request, $data['bacenta_id']);
        }

        $member = Member::create([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'phone_number'  => $data['phone_number'] ?? null,
            'gender'        => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'member_type'   => $data['member_type'] ?? null,
            'is_active'     => true,
            'member_since'  => now(),
        ]);

        if (! empty($data['bacenta_id'])) {
            $member->groups()->attach($data['bacenta_id'], [
                'joined_at'  => now(),
                'is_primary' => true,
            ]);
        }

        return $this->ok($member->load('groups:id,name'));
    }

    public function updateMember(Request $request, int $id): JsonResponse
    {
        $member = $this->scopedMember($request, $id);

        $data = $request->validate([
            'first_name'    => 'sometimes|string|max:100',
            'last_name'     => 'sometimes|string|max:100',
            'phone_number'  => 'sometimes|nullable|string|max:30',
            'gender'        => 'sometimes|nullable|string|in:male,female',
            'date_of_birth' => 'sometimes|nullable|date',
            'member_type'   => 'sometimes|nullable|string|max:50',
            'is_active'     => 'sometimes|boolean',
        ]);

        $member->update($data);

        return $this->ok($member->fresh()->load('groups:id,name'));
    }

    public function deactivateMember(Request $request, int $id): JsonResponse
    {
        $this->scopedMember($request, $id)->update(['is_active' => false]);
        return $this->ok(['message' => 'Member deactivated']);
    }

    // ─── Bacentas ───────────────────────────────────────────────────────────

    public function listBacentas(Request $request): JsonResponse
    {
        $bacentaIds = $this->scopedBacentaIds($request);
        $search = $request->query('search');

        $query = Group::whereIn('id', $bacentaIds)->withCount('members');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->ok($query->get());
    }

    public function showBacenta(Request $request, int $id): JsonResponse
    {
        return $this->ok($this->scopedBacenta($request, $id)->loadCount('members'));
    }

    public function createBacenta(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150']);

        $parentId = $this->adminGroupId($request);
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

        $bacenta = Group::create([
            'name'          => $data['name'],
            'parent_id'     => $parentId,
            'group_type_id' => $cellGroupTypeId,
            'is_active'     => true,
        ]);

        return $this->ok($bacenta->loadCount('members'));
    }

    public function updateBacenta(Request $request, int $id): JsonResponse
    {
        $bacenta = $this->scopedBacenta($request, $id);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:150',
            'is_active' => 'sometimes|boolean',
        ]);

        $bacenta->update($data);

        return $this->ok($bacenta->fresh()->loadCount('members'));
    }

    public function deactivateBacenta(Request $request, int $id): JsonResponse
    {
        $this->scopedBacenta($request, $id)->update(['is_active' => false]);
        return $this->ok(['message' => 'Bacenta deactivated']);
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
