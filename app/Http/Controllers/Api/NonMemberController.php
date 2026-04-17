<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NonMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NonMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NonMember::active();

        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $nonMembers = $query->orderBy('first_name')->get();

        return response()->json([
            'success' => true,
            'data' => $nonMembers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'group_id' => 'nullable|exists:groups,id',
            'notes' => 'nullable|string',
        ]);

        $nonMember = NonMember::create($validated);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'group_id' => 'nullable|exists:groups,id',
            'notes' => 'nullable|string',
        ]);

        $nonMember->update($validated);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);
        $nonMember->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Non-member deactivated.',
        ]);
    }
}
