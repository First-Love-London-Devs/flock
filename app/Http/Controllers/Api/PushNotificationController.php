<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function storeToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string|in:ios,android',
            'leader_id' => 'nullable|exists:leaders,id',
        ]);

        $pushToken = PushToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id' => $request->user()?->id,
                'leader_id' => $request->leader_id,
                'device_type' => $request->device_type,
                'is_active' => true,
            ]
        );

        return response()->json(['success' => true, 'data' => $pushToken]);
    }

    public function removeToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        PushToken::where('token', $request->token)->delete();

        return response()->json(['success' => true, 'message' => 'Token removed']);
    }

    public function send(Request $request, PushNotificationService $service): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'leader_id' => 'nullable|exists:leaders,id',
            'group_id' => 'nullable|exists:groups,id',
            'role_slug' => 'nullable|string',
        ]);

        try {
            if ($request->leader_id) {
                $result = $service->sendToLeader($request->leader_id, $request->title, $request->body);
            } elseif ($request->group_id) {
                $result = $service->sendToGroupLeaders($request->group_id, $request->title, $request->body);
            } elseif ($request->role_slug) {
                $result = $service->sendToRoleHolders($request->role_slug, $request->title, $request->body);
            } else {
                return response()->json(['success' => false, 'message' => 'Specify leader_id, group_id, or role_slug'], 422);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
