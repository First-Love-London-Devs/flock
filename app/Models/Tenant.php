<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $incrementing = false;
    protected $keyType = 'string';

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'church_name',
            'contact_email',
            'contact_phone',
            'plan',
            'is_active',
            'trial_ends_at',
        ];
    }

    protected $casts = [
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function suspend(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
}
