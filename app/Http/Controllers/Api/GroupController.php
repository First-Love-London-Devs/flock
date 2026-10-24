<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupHierarchyService;
use App\Services\LeaderScopeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        protected GroupHierarchyService $hierarchyService,
        protected LeaderScopeService $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Group::with(['groupType', 'leader.member'])
                ->withCount('members');

            $this->scope->scopeGroupsQuery($query);

            if ($request->has('group_type_id')) {
                $query->where('group_type_id', $request->group_type_id);
            }

            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            $groups = $query->get();

            return response()->json([
                'success' => true,
                'data' => $groups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch groups.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_type_id' => 'required|exists:group_types,id',
            'parent_id' => 'nullable|exists:groups,id',
            'leader_id' => 'nullable|exists:leaders,id',
            'description' => 'nullable|string',
            'meeting_day' => 'nullable|integer|min:0|max:6',
            'meeting_time' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $group = Group::create($validated);

            return response()->json([
                'success' => true,
                'data' => $group->load('groupType', 'leader.member', 'parent'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $group = Group::with(['groupType', 'leader.member', 'parent', 'children'])
                ->withCount('members')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $group,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $group = Group::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'group_type_id' => 'sometimes|required|exists:group_types,id',
                'parent_id' => 'nullable|exists:groups,id',
                'leader_id' => 'nullable|exists:leaders,id',
                'description' => 'nullable|string',
                'meeting_day' => 'nullable|integer|min:0|max:6',
                'meeting_time' => 'nullable|string',
                'address' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'is_active' => 'boolean',
            ]);

            $group->update($validated);

            return response()->json([
                'success' => true,
                'data' => $group->load('groupType', 'leader.member', 'parent'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update group.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $group = Group::findOrFail($id);
            $group->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group.',
            ], 500);
        }
    }

    public function children(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $children = Group::where('parent_id', $id)
                ->with(['groupType', 'leader.member'])
                ->withCount('members')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $children,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch children.',
            ], 500);
        }
    }

    public function ancestors(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $ancestors = $this->hierarchyService->getAncestors($id);

            return response()->json([
                'success' => true,
                'data' => $ancestors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ancestors.',
            ], 500);
        }
    }

    public function members(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $group = Group::findOrFail($id);
            $members = $group->members()->paginate(25);

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch members.',
            ], 500);
        }
    }

    public function hierarchy(int $id): JsonResponse
    {
        $this->authorizeGroupAccess($id);

        try {
            $group = Group::findOrFail($id);
            $tree = $this->hierarchyService->getTree($group->group_type_id);

            return response()->json([
                'success' => true,
                'data' => $tree,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hierarchy.',
            ], 500);
        }
    }

    /**
     * Abort 403 if the group exists but falls outside the caller's reach.
     *
     * A group that does not exist is left to the method's own findOrFail (404)
     * rather than leaking existence as a 403. Methods that don't findOrFail
     * (children/ancestors) simply return nothing for an unknown id.
     */
    private function authorizeGroupAccess(int $id): void
    {
        if (! Group::whereKey($id)->exists()) {
            return;
        }

        abort_unless($this->scope->canAccessGroup($id), 403, 'This group is outside your scope.');
    }
}
