<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugTenant extends Command
{
    protected $signature = 'tenant:debug {tenant_id}';
    protected $description = 'Debug tenant database and users';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant not found");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->church_name}");
        $this->info("Domain: " . $tenant->domains->pluck('domain')->implode(', '));

        $tenant->run(function () {
            $dbName = DB::connection()->getDatabaseName();
            $this->info("Database: {$dbName}");

            $tables = DB::select('SHOW TABLES');
            $this->info("Tables: " . count($tables));
            foreach ($tables as $table) {
                $name = array_values((array) $table)[0];
                $this->line("  - {$name}");
            }

            $users = DB::table('users')->get();
            $this->info("Users: " . $users->count());
            foreach ($users as $user) {
                $this->line("  - ID:{$user->id} Email:{$user->email} Password length:" . strlen($user->password));
            }
        });

        return self::SUCCESS;
    }
}
