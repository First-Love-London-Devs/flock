<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && !$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This church account has been suspended. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
