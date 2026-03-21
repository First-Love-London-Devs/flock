<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class SetSelectedTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = session('selected_tenant_id');

        if ($tenantId && $tenant = Tenant::find($tenantId)) {
            tenancy()->initialize($tenant);
        }

        return $next($request);
    }
}
