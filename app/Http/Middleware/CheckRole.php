<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->user_type, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Required role: ' . implode(' or ', $roles),
            ], 403);
        }

        return $next($request);
    }
}
