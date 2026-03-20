<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyIfNotCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', []);
        $currentDomain = $request->getHost();

        // Skip tenancy initialization for central domains
        if (in_array($currentDomain, $centralDomains)) {
            return $next($request);
        }

        // Initialize tenancy for tenant domains
        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
