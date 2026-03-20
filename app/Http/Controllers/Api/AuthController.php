<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $leader = Leader::where('username', $request->username)
            ->where('is_active', true)
            ->first();

        if (!$leader || !Hash::check($request->password, $leader->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $leader->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'leader' => $leader->load('member', 'leaderRoles.roleDefinition', 'leaderRoles.group'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}
