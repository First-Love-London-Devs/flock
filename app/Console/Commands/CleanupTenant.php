<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTenant extends Command
{
    protected $signature = 'tenant:cleanup {tenant_id}';
    protected $description = 'Force remove a broken tenant record and its database';

    public function handle(): int
    {
        $id = $this->argument('tenant_id');

        $this->info("Cleaning up tenant: {$id}");

        // Delete domain records
        $domains = DB::table('domains')->where('tenant_id', $id)->delete();
        $this->info("Deleted {$domains} domain(s)");

        // Delete tenant record
        $tenants = DB::table('tenants')->where('id', $id)->delete();
        $this->info("Deleted {$tenants} tenant record(s)");

        // Try to drop databases
        $dbNames = ["tenant_{$id}", "tenant{$id}", "tenant0"];
        foreach ($dbNames as $dbName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
                $this->info("Dropped database: {$dbName}");
            } catch (\Exception $e) {
                $this->warn("Could not drop {$dbName}: {$e->getMessage()}");
            }
        }

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }
}
