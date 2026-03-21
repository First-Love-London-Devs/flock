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
            app()->instance('selected_tenant', $tenant);
        }

        return $next($request);
    }

    public static function getSelectedTenant(): ?Tenant
    {
        return app()->bound('selected_tenant') ? app('selected_tenant') : null;
    }
}
