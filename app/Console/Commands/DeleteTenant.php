<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class DeleteTenant extends Command
{
    protected $signature = 'tenant:delete
        {tenant_id : The tenant ID to delete}
        {--force : Skip confirmation}';

    protected $description = 'Delete a tenant and its database';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete '{$tenant->church_name}'? This will drop the database.")) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("Deleting tenant: {$tenant->church_name}...");
        $tenant->delete();
        $this->info('Tenant deleted successfully.');

        return self::SUCCESS;
    }
}
