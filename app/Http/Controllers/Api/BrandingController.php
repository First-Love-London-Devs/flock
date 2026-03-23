<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'church_name' => Setting::get('church_name', $tenant->church_name),
                'tagline' => Setting::get('tagline'),
                'color_primary' => Setting::get('color_primary'),
                'color_secondary' => Setting::get('color_secondary'),
                'logo' => Setting::get('logo'),
                'logo_dark' => Setting::get('logo_dark'),
            ],
        ]);
    }
}
