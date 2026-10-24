<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\LeaderScopeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function __construct(
        protected LeaderScopeService $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Member::query();
            $this->scope->scopeMembersQuery($query);

            if ($request->has('group_id')) {
                $query->whereHas('groups', fn ($q) => $q->where('groups.id', $request->group_id));
            }

            if ($request->boolean('unassigned')) {
                $query->whereDoesntHave('groups');
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            }

            $members = $query->paginate(25);

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch members.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:members,email',
            'phone_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female',
            'address' => 'nullable|string',
            'picture' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'occupation' => 'nullable|string|max:255',
            'nbs_status' => 'nullable|string|in:'.implode(',', array_keys(Member::NBS_STATUSES)),
            'holy_ghost_baptism' => 'boolean',
            'water_baptism' => 'boolean',
            'member_type' => 'nullable|string|in:'.implode(',', array_keys(Member::MEMBER_TYPES)),
            'profile_completed' => 'boolean',
            'member_since' => 'nullable|date',
            'notes' => 'nullable|string',
            'additional_info' => 'nullable|array',
        ]);

        try {
            $member = Member::create($validated);

            return response()->json([
                'success' => true,
                'data' => $member,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create member.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeMemberAccess($id);

        try {
            $member = Member::with(['groups', 'leader'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $member,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch member.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeMemberAccess($id);

        try {
            $member = Member::findOrFail($id);

            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|unique:members,email,'.$id,
                'phone_number' => 'nullable|string|max:50',
                'date_of_birth' => 'nullable|date',
                'gender' => 'nullable|string|in:male,female',
                'address' => 'nullable|string',
                'street_name' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:50',
                'picture' => 'nullable|string',
                'marital_status' => 'nullable|string',
                'occupation' => 'nullable|string|max:255',
                'nbs_status' => 'nullable|string|in:'.implode(',', array_keys(Member::NBS_STATUSES)),
                'holy_ghost_baptism' => 'boolean',
                'water_baptism' => 'boolean',
                'strong_christian_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'school_of_the_word_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'school_of_solid_foundation_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'school_of_victorious_living_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'school_of_apologetics_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'school_of_evangelism_status' => 'nullable|string|in:'.implode(',', array_keys(Member::GROWTH_TRACK_STATUSES)),
                'member_type' => 'nullable|string|in:'.implode(',', array_keys(Member::MEMBER_TYPES)),
                'profile_completed' => 'boolean',
                'member_since' => 'nullable|date',
                'is_active' => 'boolean',
                'notes' => 'nullable|string',
                'additional_info' => 'nullable|array',
            ]);

            $member->update($validated);

            return response()->json([
                'success' => true,
                'data' => $member,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update member.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeMemberAccess($id);

        try {
            $member = Member::findOrFail($id);
            $member->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete member.',
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:1',
            ]);

            $search = $request->q;

            $query = Member::query();
            $this->scope->scopeMembersQuery($query);
            $members = $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            })
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search members.',
            ], 500);
        }
    }

    public function assignGroup(Request $request, int $id): JsonResponse
    {
        $this->authorizeMemberAccess($id);

        // Validate and authorise the target group BEFORE the try: an abort()
        // inside it would be swallowed by the generic catch and returned as 500.
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'is_primary' => 'boolean',
        ]);

        abort_unless(
            $this->scope->canAccessGroup((int) $validated['group_id']),
            403,
            'That group is outside your scope.'
        );

        try {
            $member = Member::findOrFail($id);

            $member->groups()->attach($validated['group_id'], [
                'joined_at' => now()->toDateString(),
                'is_primary' => $validated['is_primary'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $member->load('groups'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign group.',
            ], 500);
        }
    }

    public function removeGroup(int $id, int $groupId): JsonResponse
    {
        $this->authorizeMemberAccess($id);

        try {
            $member = Member::findOrFail($id);
            $member->groups()->detach($groupId);

            return response()->json([
                'success' => true,
                'data' => $member->load('groups'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove group.',
            ], 500);
        }
    }

    /**
     * Abort 403 if the member exists but falls outside the caller's scope.
     *
     * Reuses the exact query the list/search endpoints scope with, so a
     * per-record read or write can never reach further than the list would.
     * A member that does not exist is left to the method's own findOrFail,
     * which returns 404 rather than leaking existence as a 403.
     */
    private function authorizeMemberAccess(int $id): void
    {
        if (! Member::whereKey($id)->exists()) {
            return;
        }

        $scoped = Member::whereKey($id);
        $this->scope->scopeMembersQuery($scoped);

        abort_unless($scoped->exists(), 403, 'This member is outside your scope.');
    }
}
