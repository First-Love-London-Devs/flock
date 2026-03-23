<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'church_name' => $tenant->branding_church_name ?? $tenant->church_name,
                'tagline' => $tenant->branding_tagline ?? null,
                'color_primary' => $tenant->branding_color_primary ?? null,
                'color_secondary' => $tenant->branding_color_secondary ?? null,
                'logo' => $tenant->branding_logo ?? null,
                'logo_dark' => $tenant->branding_logo_dark ?? null,
            ],
        ]);
    }
}
