<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leader;
use App\Models\LeaderRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Leader::with(['member', 'leaderRoles.roleDefinition', 'ledGroup']);

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            $leaders = $query->get();

            return response()->json([
                'success' => true,
                'data' => $leaders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leaders.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id|unique:leaders,member_id',
            'username' => 'required|string|max:255|unique:leaders,username',
            'password' => 'required|string|min:8',
        ]);

        try {
            $leader = Leader::create($validated);

            return response()->json([
                'success' => true,
                'data' => $leader->load('member'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leader.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $leader = Leader::with(['member', 'leaderRoles.roleDefinition', 'leaderRoles.group', 'ledGroup'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $leader,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leader not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leader.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $leader = Leader::findOrFail($id);

            $validated = $request->validate([
                'username' => 'sometimes|required|string|max:255|unique:leaders,username,' . $id,
                'password' => 'sometimes|required|string|min:8',
                'is_active' => 'boolean',
                'notification_token' => 'nullable|string',
            ]);

            $leader->update($validated);

            return response()->json([
                'success' => true,
                'data' => $leader->load('member'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leader not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leader.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $leader = Leader::findOrFail($id);
            $leader->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leader not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leader.',
            ], 500);
        }
    }

    public function assignRole(Request $request, int $id): JsonResponse
    {
        try {
            $leader = Leader::findOrFail($id);

            $validated = $request->validate([
                'role_definition_id' => 'required|exists:role_definitions,id',
                'group_id' => 'nullable|exists:groups,id',
            ]);

            $leaderRole = LeaderRole::create([
                'leader_id' => $leader->id,
                'role_definition_id' => $validated['role_definition_id'],
                'group_id' => $validated['group_id'] ?? null,
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $leaderRole->load('roleDefinition', 'group'),
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leader not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role.',
            ], 500);
        }
    }

    public function removeRole(int $id, int $roleId): JsonResponse
    {
        try {
            Leader::findOrFail($id);
            $leaderRole = LeaderRole::where('leader_id', $id)->findOrFail($roleId);
            $leaderRole->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leader or role not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role.',
            ], 500);
        }
    }
}
