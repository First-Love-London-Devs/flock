<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Leader;
use App\Models\Member;
use Illuminate\Console\Command;

class CreateTenantLeader extends Command
{
    protected $signature = 'tenant:create-leader
        {tenant_id : The tenant ID}
        {--first-name=John : First name}
        {--last-name=Smith : Last name}
        {--email=john@test.com : Email}
        {--username=jsmith : Leader username}
        {--password=leader123 : Leader password}';

    protected $description = 'Create a test member and leader for a tenant';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant_id'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $tenant->run(function () {
            $member = Member::firstOrCreate(
                ['email' => $this->option('email')],
                [
                    'first_name' => $this->option('first-name'),
                    'last_name' => $this->option('last-name'),
                    'is_active' => true,
                    'member_since' => now(),
                ]
            );

            $leader = Leader::firstOrCreate(
                ['username' => $this->option('username')],
                [
                    'member_id' => $member->id,
                    'password' => $this->option('password'),
                    'is_active' => true,
                ]
            );

            $this->info("Member: {$member->full_name} (ID: {$member->id})");
            $this->info("Leader: {$leader->username} (ID: {$leader->id})");
        });

        return self::SUCCESS;
    }
}
