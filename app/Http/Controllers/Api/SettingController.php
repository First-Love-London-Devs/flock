<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $settings = Setting::all();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings.',
            ], 500);
        }
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
            'type' => 'nullable|string|in:string,boolean,integer,json',
        ]);

        try {
            Setting::set($key, $validated['value'], $validated['type'] ?? 'string');

            return response()->json([
                'success' => true,
                'data' => ['key' => $key, 'value' => $validated['value']],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting.',
            ], 500);
        }
    }
}
