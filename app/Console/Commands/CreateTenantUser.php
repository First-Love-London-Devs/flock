<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTenantUser extends Command
{
    protected $signature = 'tenant:create-user
        {tenant_id : The tenant ID}
        {--email= : User email}
        {--name=Admin : User name}
        {--password=flock2026 : User password}';

    protected $description = 'Create or reset an admin user for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        $email = $this->option('email') ?? $tenant->contact_email ?? 'admin@church.com';
        $name = $this->option('name');
        $password = $this->option('password');

        $tenant->run(function () use ($email, $name, $password) {
            // Delete existing user with this email and recreate
            User::where('email', $email)->delete();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // User model has 'hashed' cast, auto-hashes
                'email_verified_at' => now(),
            ]);

            $this->info("User created: {$user->email} (ID: {$user->id})");
        });

        return self::SUCCESS;
    }
}
