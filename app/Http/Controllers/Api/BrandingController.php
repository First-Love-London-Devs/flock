<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = tenant();

        $logo = Setting::get('church_logo');
        $logoDark = Setting::get('church_logo_dark');

        // Build full URL for logos if they exist
        $baseUrl = $request->getSchemeAndHttpHost();

        return response()->json([
            'success' => true,
            'data' => [
                'church_name' => Setting::get('church_name', $tenant->church_name),
                'tagline' => Setting::get('church_tagline'),
                'color_primary' => Setting::get('color_primary'),
                'color_secondary' => Setting::get('color_secondary'),
                'logo' => $logo ? $baseUrl . $logo : null,
                'logo_dark' => $logoDark ? $baseUrl . $logoDark : null,
            ],
        ]);
    }
}
