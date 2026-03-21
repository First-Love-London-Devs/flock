<?php

namespace App\Filament\Central\Resources;

use Filament\Resources\Resource;

abstract class TenantScopedResource extends Resource
{
    public static function canAccess(): bool
    {
        return session()->has('selected_tenant_id');
    }
}
