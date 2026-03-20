<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupTypeController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $groupTypes = GroupType::orderBy('level')->get();

            return response()->json([
                'success' => true,
                'data' => $groupTypes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group types.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:group_types,slug',
            'description' => 'nullable|string',
            'level' => 'required|integer|min:0',
            'tracks_attendance' => 'boolean',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
        ]);

        try {
            $groupType = GroupType::create($validated);

            return response()->json([
                'success' => true,
                'data' => $groupType,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group type.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $groupType = GroupType::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $groupType,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group type not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group type.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $groupType = GroupType::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|max:255|unique:group_types,slug,' . $id,
                'description' => 'nullable|string',
                'level' => 'sometimes|required|integer|min:0',
                'tracks_attendance' => 'boolean',
                'color' => 'nullable|string|max:50',
                'icon' => 'nullable|string|max:50',
            ]);

            $groupType->update($validated);

            return response()->json([
                'success' => true,
                'data' => $groupType,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group type not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update group type.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $groupType = GroupType::findOrFail($id);

            if ($groupType->groups()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group type that has groups assigned to it.',
                ], 422);
            }

            $groupType->delete();

            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group type not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group type.',
            ], 500);
        }
    }
}
