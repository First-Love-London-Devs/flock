<?php

namespace App\Http\Middleware;

use App\Services\LeaderScopeService;
use Closure;
use Illuminate\Http\Request;

class InitializeLeaderScope
{
    public function handle(Request $request, Closure $next)
    {
        $leader = $request->user();

        if ($leader) {
            app(LeaderScopeService::class)->setLeader($leader);
        }

        return $next($request);
    }
}
