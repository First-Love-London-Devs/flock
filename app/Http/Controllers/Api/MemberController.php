<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Member::query();

            if ($request->has('group_id')) {
                $query->whereHas('groups', fn ($q) => $q->where('groups.id', $request->group_id));
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
            'nbs_status' => 'nullable|string|in:not_started,in_progress,completed',
            'holy_ghost_baptism' => 'boolean',
            'water_baptism' => 'boolean',
            'member_type' => 'nullable|string|in:member,visitor,first_timer,new_convert',
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
        try {
            $member = Member::with(['groups', 'leader'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $member,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
        try {
            $member = Member::findOrFail($id);

            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|unique:members,email,' . $id,
                'phone_number' => 'nullable|string|max:50',
                'date_of_birth' => 'nullable|date',
                'gender' => 'nullable|string|in:male,female',
                'address' => 'nullable|string',
                'picture' => 'nullable|string',
                'marital_status' => 'nullable|string',
                'occupation' => 'nullable|string|max:255',
                'nbs_status' => 'nullable|string|in:not_started,in_progress,completed',
                'holy_ghost_baptism' => 'boolean',
                'water_baptism' => 'boolean',
                'member_type' => 'nullable|string|in:member,visitor,first_timer,new_convert',
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update member.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $member = Member::findOrFail($id);
            $member->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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

            $members = Member::where(function ($q) use ($search) {
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
        try {
            $member = Member::findOrFail($id);

            $validated = $request->validate([
                'group_id' => 'required|exists:groups,id',
                'is_primary' => 'boolean',
            ]);

            $member->groups()->attach($validated['group_id'], [
                'joined_at' => now()->toDateString(),
                'is_primary' => $validated['is_primary'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $member->load('groups'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
        try {
            $member = Member::findOrFail($id);
            $member->groups()->detach($groupId);

            return response()->json([
                'success' => true,
                'data' => $member->load('groups'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
}
