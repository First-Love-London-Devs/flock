<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ListTenants extends Command
{
    protected $signature = 'tenant:list';
    protected $description = 'List all tenants';

    public function handle(): int
    {
        $tenants = Tenant::with('domains')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Church Name', 'Domain', 'Plan', 'Active', 'Created'],
            $tenants->map(fn ($t) => [
                $t->id,
                $t->church_name,
                $t->domains->pluck('domain')->implode(', '),
                $t->plan,
                $t->is_active ? 'Yes' : 'No',
                $t->created_at?->format('Y-m-d'),
            ])
        );

        return self::SUCCESS;
    }
}
