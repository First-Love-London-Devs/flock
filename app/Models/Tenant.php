<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

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

    // Override GeneratesIds trait — we set IDs manually as strings
    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * The church's local IANA timezone (e.g. 'Europe/Brussels'). Stored in the
     * tenant's data column; falls back to the app-wide church default. Used to
     * interpret admin-entered times such as attendance service windows.
     */
    public function getTimezone(): string
    {
        return $this->timezone ?: config('church.timezone', 'Europe/London');
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
