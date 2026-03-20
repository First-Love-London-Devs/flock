<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {church_name : The name of the church}
        {--domain= : The domain for the tenant (required)}
        {--email= : Contact email}
        {--plan=free : Plan (free, starter, pro)}';

    protected $description = 'Create a new tenant (church) with its own database';

    public function handle(): int
    {
        $churchName = $this->argument('church_name');
        $domain = $this->option('domain');

        if (!$domain) {
            $this->error('The --domain option is required.');
            return self::FAILURE;
        }

        $tenantId = Str::slug($churchName);

        if (Tenant::find($tenantId)) {
            $this->error("Tenant '{$tenantId}' already exists.");
            return self::FAILURE;
        }

        $this->info("Creating tenant: {$churchName}...");

        $tenant = Tenant::create([
            'id' => $tenantId,
            'church_name' => $churchName,
            'contact_email' => $this->option('email'),
            'plan' => $this->option('plan'),
            'is_active' => true,
        ]);

        $tenant->domains()->create(['domain' => $domain]);

        $this->info("Tenant created successfully!");
        $this->table(
            ['ID', 'Church Name', 'Domain', 'Plan'],
            [[$tenant->id, $tenant->church_name, $domain, $tenant->plan]]
        );

        return self::SUCCESS;
    }
}
